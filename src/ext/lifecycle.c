/*
   +----------------------------------------------------------------------+
   | Elastic APM agent for PHP                                            |
   +----------------------------------------------------------------------+
   | Copyright (c) 2020 Elasticsearch B.V.                                |
   +----------------------------------------------------------------------+
   | Elasticsearch B.V. licenses this file under the Apache 2.0 License.  |
   | See the LICENSE file in the project root for more information.       |
   +----------------------------------------------------------------------+
 */

#include "lifecycle.h"
#if defined(PHP_WIN32) && ! defined(CURL_STATICLIB)
#   define CURL_STATICLIB
#endif
#include <curl/curl.h>
#include <inttypes.h> // PRIu64
#include <stdbool.h>
#include <php.h>
#include <zend_compile.h>
#include <zend_exceptions.h>
#include "php_elasticapm.h"
#include "log.h"
#include "SystemMetrics.h"
#include "php_error.h"
#include "util_for_php.h"
#include "elasticapm_assert.h"
#include "MemoryTracker.h"
#include "supportability.h"
#include "elasticapm_alloc.h"
#include "elasticapm_API.h"

static const char JSON_METADATA[] =
        "{\"metadata\":{\"process\":{\"pid\":%d},\"service\":{\"name\":\"%s\",\"language\":{\"name\":\"php\"},\"agent\":{\"version\":\"%s\",\"name\":\"php\"}}}}\n";
static const char JSON_METRICSET[] =
        "{\"metricset\":{\"samples\":{\"system.cpu.total.norm.pct\":{\"value\":%.2f},\"system.process.cpu.total.norm.pct\":{\"value\":%.2f},\"system.memory.actual.free\":{\"value\":%"PRIu64"},\"system.memory.total\":{\"value\":%"PRIu64"},\"system.process.memory.size\":{\"value\":%"PRIu64"},\"system.process.memory.rss.bytes\":{\"value\":%"PRIu64"}},\"timestamp\":%"PRIu64"}}\n";

static
String buildSupportabilityInfo( size_t supportInfoBufferSize, char* supportInfoBuffer )
{
    TextOutputStream txtOutStream = makeTextOutputStream( supportInfoBuffer, supportInfoBufferSize );
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( &txtOutStream, &txtOutStreamStateOnEntryStart ) )
    {
        return ELASTICAPM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;
    }

    StructuredTextToOutputStreamPrinter structTxtToOutStreamPrinter;
    initStructuredTextToOutputStreamPrinter(
            /* in */ &txtOutStream
                     , /* prefix */ ELASTICAPM_STRING_LITERAL_TO_VIEW( "" )
                     , /* out */ &structTxtToOutStreamPrinter );

    printSupportabilityInfo( (StructuredTextPrinter*) &structTxtToOutStreamPrinter );

    return textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, &txtOutStream );
}

void logSupportabilityInfo( LogLevel logLevel )
{
    ResultCode resultCode;
    enum
    {
        supportInfoBufferSize = 100 * 1000 + 1
    };
    char* supportInfoBuffer = NULL;

    ELASTICAPM_PEMALLOC_STRING_IF_FAILED_GOTO( supportInfoBufferSize, supportInfoBuffer );
    String supportabilityInfo = buildSupportabilityInfo( supportInfoBufferSize, supportInfoBuffer );

    ELASTICAPM_LOG_WITH_LEVEL( logLevel, "Supportability info:\n%s", supportabilityInfo );

    // resultCode = resultSuccess;

    finally:
    ELASTICAPM_PEFREE_STRING_AND_SET_TO_NULL( supportInfoBufferSize, supportInfoBuffer );
    ELASTICAPM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}

void elasticApmModuleInit( int type, int moduleNumber )
{
    ResultCode resultCode;
    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* config = NULL;

    ELASTICAPM_CALL_IF_FAILED_GOTO( constructTracer( tracer ) );

    ELASTICAPM_LOG_DEBUG_FUNCTION_ENTRY();

    registerElasticApmIniEntries( moduleNumber, &tracer->iniEntriesRegistrationState );

    ELASTICAPM_CALL_IF_FAILED_GOTO( ensureAllComponentsHaveLatestConfig( tracer ) );
    logSupportabilityInfo( logLevel_debug );
    config = getTracerCurrentConfigSnapshot( tracer );

    if ( ! config->enabled )
    {
        ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "Because extension is not enabled" );
        goto finally;
    }

    CURLcode result = curl_global_init( CURL_GLOBAL_ALL );
    if ( result != CURLE_OK )
    {
        resultCode = resultFailure;
        ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "curl_global_init failed" );
        goto finally;
    }
    tracer->curlInited = true;

    resultCode = resultSuccess;
    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT();

    finally:

    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    ELASTICAPM_UNUSED( resultCode );
    return;

    failure:
    moveTracerToFailedState( tracer );
    goto finally;
}

void elasticApmModuleShutdown( int type, int moduleNumber )
{
    ELASTICAPM_UNUSED( type );

    ResultCode resultCode;

    ELASTICAPM_LOG_DEBUG_FUNCTION_ENTRY();

    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* const config = getTracerCurrentConfigSnapshot( tracer );

    if ( ! config->enabled )
    {
        ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "Because extension is not enabled" );
        goto finally;
    }

    if ( tracer->curlInited )
    {
        curl_global_cleanup();
        tracer->curlInited = false;
    }

    unregisterElasticApmIniEntries( moduleNumber, &tracer->iniEntriesRegistrationState );

    resultCode = resultSuccess;
    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT();

    finally:
    destructTracer( tracer );

    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    ELASTICAPM_UNUSED( resultCode );
}

#define ELASTICAPM_PHP_PART_BOOTSTRAP_FUNC_NAMESPACE "\\Elastic\\Apm\\Impl\\AutoInstrument\\"
#define ELASTICAPM_PHP_PART_BOOTSTRAP_FUNC ELASTICAPM_PHP_PART_BOOTSTRAP_FUNC_NAMESPACE "bootstrapTracerPhpPart"
#define ELASTICAPM_PHP_PART_SHUTDOWN_FUNC ELASTICAPM_PHP_PART_BOOTSTRAP_FUNC_NAMESPACE "shutdownTracerPhpPart"

static
ResultCode bootstrapPhpPart( const ConfigSnapshot* config )
{
    char txtOutStreamBuf[ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    ELASTICAPM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "config->bootstrapPhpPartFile: %s"
                                             , streamUserString( config->bootstrapPhpPartFile, &txtOutStream ) );

    ResultCode resultCode;
    bool bootstrapTracerPhpPartRetVal;
    zval maxEnabledLevel;
    ZVAL_UNDEF( &maxEnabledLevel );

    if ( config->bootstrapPhpPartFile == NULL )
    {
        // For now we don't consider option not being set as a failure
        GetConfigManagerOptionMetadataResult getMetaRes;
        getConfigManagerOptionMetadata( getGlobalTracer()->configManager, optionId_bootstrapPhpPartFile, &getMetaRes );
        ELASTICAPM_LOG_INFO( "Configuration option `%s' is not set", getMetaRes.optName );
        resultCode = resultSuccess;
        goto finally;
    }

    ELASTICAPM_CALL_IF_FAILED_GOTO( loadPhpFile( config->bootstrapPhpPartFile ) );

    ZVAL_LONG( &maxEnabledLevel, getGlobalTracer()->logger.maxEnabledLevel )
    zval bootstrapTracerPhpPartArgs[] = { maxEnabledLevel };
    ELASTICAPM_CALL_IF_FAILED_GOTO( callPhpFunctionRetBool(
            ELASTICAPM_STRING_LITERAL_TO_VIEW( ELASTICAPM_PHP_PART_BOOTSTRAP_FUNC )
            , logLevel_debug
            , /* argsCount */ ELASTICAPM_STATIC_ARRAY_SIZE( bootstrapTracerPhpPartArgs )
            , /* args */ bootstrapTracerPhpPartArgs
            , &bootstrapTracerPhpPartRetVal ) );
    if ( ! bootstrapTracerPhpPartRetVal )
    {
        ELASTICAPM_LOG_CRITICAL( "%s failed (returned false). See log for more details.", ELASTICAPM_PHP_PART_BOOTSTRAP_FUNC );
        resultCode = resultFailure;
        goto failure;
    }

    resultCode = resultSuccess;

    finally:
    zval_dtor( &maxEnabledLevel );
    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    return resultCode;

    failure:
    goto finally;
}


static
void shutdownPhpPart( const ConfigSnapshot* config )
{
    ELASTICAPM_LOG_DEBUG_FUNCTION_ENTRY();

    ResultCode resultCode;

    if ( config->bootstrapPhpPartFile == NULL )
    {
        // For now we don't consider option not being set as a failure
        GetConfigManagerOptionMetadataResult getMetaRes;
        getConfigManagerOptionMetadata( getGlobalTracer()->configManager, optionId_bootstrapPhpPartFile, &getMetaRes );
        ELASTICAPM_LOG_INFO( "Configuration option `%s' is not set", getMetaRes.optName );
        resultCode = resultSuccess;
        goto finally;
    }

    ELASTICAPM_CALL_IF_FAILED_GOTO( callPhpFunctionRetVoid(
            ELASTICAPM_STRING_LITERAL_TO_VIEW( ELASTICAPM_PHP_PART_SHUTDOWN_FUNC )
            , logLevel_debug
            , /* argsCount */ 0
            , /* args */ NULL ) );

    resultCode = resultSuccess;

    finally:
    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    ELASTICAPM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}

void elasticApmRequestInit()
{
#if defined(ZTS) && defined(COMPILE_DL_ELASTICAPM)
    ZEND_TSRMLS_CACHE_UPDATE();
#endif

    ELASTICAPM_LOG_DEBUG_FUNCTION_ENTRY();

    ResultCode resultCode;
    Tracer* const tracer = getGlobalTracer();

    if ( isMemoryTrackingEnabled( &tracer->memTracker ) ) memoryTrackerRequestInit( &tracer->memTracker );

    if ( ! tracer->isInited )
    {
        resultCode = resultFailure;
        ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "Extension is not initialized" );
        goto finally;
    }

    if ( ! getTracerCurrentConfigSnapshot( tracer )->enabled )
    {
        resultCode = resultSuccess;
        ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "Because extension is not enabled" );
        goto finally;
    }

    ELASTICAPM_CALL_IF_FAILED_GOTO( ensureAllComponentsHaveLatestConfig( tracer ) );
    logSupportabilityInfo( logLevel_trace );

    const ConfigSnapshot* config = getTracerCurrentConfigSnapshot( tracer );

    ELASTICAPM_CALL_IF_FAILED_GOTO( bootstrapPhpPart( config ) );

    readSystemMetrics( &tracer->startSystemMetricsReading );

    resultCode = resultSuccess;

    finally:

    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    ELASTICAPM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}

static
void appendMetadata( const ConfigSnapshot* config, TextOutputStream* serializedEventsTxtOutStream )
{
    streamPrintf( serializedEventsTxtOutStream, JSON_METADATA, getpid(), config->serviceName, PHP_ELASTICAPM_VERSION );
}

static
void appendMetrics( const SystemMetricsReading* startSystemMetricsReading, const TimePoint* currentTime, TextOutputStream* serializedEventsTxtOutStream )
{
    SystemMetricsReading endSystemMetricsReading;
    readSystemMetrics( &endSystemMetricsReading );
    SystemMetrics system_metrics;
    getSystemMetrics( startSystemMetricsReading, &endSystemMetricsReading, &system_metrics );

    streamPrintf(
            serializedEventsTxtOutStream
            , JSON_METRICSET
            , system_metrics.machineCpu // system.cpu.total.norm.pct
            , system_metrics.processCpu // system.process.cpu.total.norm.pct
            , system_metrics.machineMemoryFree  // system.memory.actual.free
            , system_metrics.machineMemoryTotal // system.memory.total
            , system_metrics.processMemorySize  // system.process.memory.size
            , system_metrics.processMemoryRss   // system.process.memory.rss.bytes
            , timePointToEpochMicroseconds( currentTime ) );
}

void elasticApmRequestShutdown()
{
    ResultCode resultCode;
    enum
    {
        serializedEventsBufferSize = 1000 * 1000
    };
    char* serializedEventsBuffer = NULL;
    TimePoint currentTime;
    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* const config = getTracerCurrentConfigSnapshot( tracer );

    ELASTICAPM_LOG_DEBUG_FUNCTION_ENTRY();

    if ( ! tracer->isInited )
    {
        resultCode = resultFailure;
        ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "Extension is not initialized" );
        goto finally;
    }

    shutdownPhpPart( config );

    getCurrentTime( &currentTime );

    ELASTICAPM_EMALLOC_STRING_IF_FAILED_GOTO( serializedEventsBufferSize, serializedEventsBuffer );
    TextOutputStream serializedEventsTxtOutStream =
            makeTextOutputStream( serializedEventsBuffer, serializedEventsBufferSize );
    serializedEventsTxtOutStream.autoTermZero = false;

    appendMetadata( config, &serializedEventsTxtOutStream );

    appendMetrics( &tracer->startSystemMetricsReading, &currentTime, &serializedEventsTxtOutStream );

    sendEventsToApmServer( config, serializedEventsBuffer );

    resetCallInterceptionOnRequestShutdown();

    resultCode = resultSuccess;
    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT();

    finally:
    ELASTICAPM_EFREE_STRING_AND_SET_TO_NULL( serializedEventsBufferSize, serializedEventsBuffer );
    if ( isMemoryTrackingEnabled( &tracer->memTracker ) ) memoryTrackerRequestShutdown( &tracer->memTracker );

    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    ELASTICAPM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}
