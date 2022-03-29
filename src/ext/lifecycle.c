/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
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
#include "php_elastic_apm.h"
#include "log.h"
#include "SystemMetrics.h"
#include "php_error.h"
#include "util_for_PHP.h"
#include "elastic_apm_assert.h"
#include "MemoryTracker.h"
#include "supportability.h"
#include "elastic_apm_alloc.h"
#include "elastic_apm_API.h"
#include "tracer_PHP_part.h"
#include "backend_comm.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_LIFECYCLE

static const char JSON_METRICSET[] =
        "{\"metricset\":{\"samples\":{\"system.cpu.total.norm.pct\":{\"value\":%.2f},\"system.process.cpu.total.norm.pct\":{\"value\":%.2f},\"system.memory.actual.free\":{\"value\":%"PRIu64"},\"system.memory.total\":{\"value\":%"PRIu64"},\"system.process.memory.size\":{\"value\":%"PRIu64"},\"system.process.memory.rss.bytes\":{\"value\":%"PRIu64"}},\"timestamp\":%"PRIu64"}}\n";

static
String buildSupportabilityInfo( size_t supportInfoBufferSize, char* supportInfoBuffer )
{
    TextOutputStream txtOutStream = makeTextOutputStream( supportInfoBuffer, supportInfoBufferSize );
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( &txtOutStream, &txtOutStreamStateOnEntryStart ) )
    {
        return ELASTIC_APM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;
    }

    StructuredTextToOutputStreamPrinter structTxtToOutStreamPrinter;
    initStructuredTextToOutputStreamPrinter(
            /* in */ &txtOutStream
                     , /* prefix */ ELASTIC_APM_STRING_LITERAL_TO_VIEW( "" )
                     , /* out */ &structTxtToOutStreamPrinter );

    printSupportabilityInfo( (StructuredTextPrinter*) &structTxtToOutStreamPrinter );

    return textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, &txtOutStream );
}

void logSupportabilityInfo( LogLevel logLevel )
{
    ELASTIC_APM_LOG_WITH_LEVEL( logLevel, "Version of agent C part: " PHP_ELASTIC_APM_VERSION );

    ResultCode resultCode;
    enum
    {
        supportInfoBufferSize = 100 * 1000 + 1
    };
    char* supportInfoBuffer = NULL;

    ELASTIC_APM_PEMALLOC_STRING_IF_FAILED_GOTO( supportInfoBufferSize, supportInfoBuffer );
    String supportabilityInfo = buildSupportabilityInfo( supportInfoBufferSize, supportInfoBuffer );

    ELASTIC_APM_LOG_WITH_LEVEL( logLevel, "Supportability info:\n%s", supportabilityInfo );

    // resultCode = resultSuccess;

    finally:
    ELASTIC_APM_PEFREE_STRING_AND_SET_TO_NULL( supportInfoBufferSize, supportInfoBuffer );
    ELASTIC_APM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}

void elasticApmModuleInit( int type, int moduleNumber )
{
    ELASTIC_APM_UNUSED( type );

    ResultCode resultCode;
    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* config = NULL;

    registerOsSignalHandler();

    ELASTIC_APM_CALL_IF_FAILED_GOTO( constructTracer( tracer ) );

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    if ( ! tracer->isInited )
    {
        ELASTIC_APM_LOG_DEBUG( "Extension is not initialized" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    registerElasticApmIniEntries( moduleNumber, &tracer->iniEntriesRegistrationState );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( ensureAllComponentsHaveLatestConfig( tracer ) );

    logSupportabilityInfo( logLevel_debug );

    config = getTracerCurrentConfigSnapshot( tracer );

    if ( ! config->enabled )
    {
        resultCode = resultSuccess;
        ELASTIC_APM_LOG_DEBUG( "Extension is disabled" );
        goto finally;
    }

    if ( getGlobalLogger()->maxEnabledLevel >= logLevel_debug )
    {
        registerAtExitLogging();
    }

    CURLcode curlCode = curl_global_init( CURL_GLOBAL_ALL );
    if ( curlCode != CURLE_OK )
    {
        resultCode = resultFailure;
        ELASTIC_APM_LOG_ERROR( "curl_global_init failed: %s (%d)", curl_easy_strerror( curlCode ), (int)curlCode );
        goto finally;
    }
    tracer->curlInited = true;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( backgroundBackendCommOnModuleInit( config ) );

    resultCode = resultSuccess;
    finally:

    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    return;

    failure:
    moveTracerToFailedState( tracer );
    goto finally;
}

void elasticApmModuleShutdown( int type, int moduleNumber )
{
    ELASTIC_APM_UNUSED( type );

    ResultCode resultCode;

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* const config = getTracerCurrentConfigSnapshot( tracer );

    if ( ! config->enabled )
    {
        resultCode = resultSuccess;
        ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT_MSG( "Because extension is not enabled" );
        goto finally;
    }

    backgroundBackendCommOnModuleShutdown();

    if ( tracer->curlInited )
    {
        curl_global_cleanup();
        tracer->curlInited = false;
    }

    unregisterElasticApmIniEntries( moduleNumber, &tracer->iniEntriesRegistrationState );

    resultCode = resultSuccess;
    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT();

    finally:
    destructTracer( tracer );

    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    ELASTIC_APM_UNUSED( resultCode );
}

typedef void (* ZendThrowExceptionHook )(
#if PHP_MAJOR_VERSION >= 8 /* if PHP version is 8.* and later */
        zend_object* exception
#else
        zval* exception
#endif
);

static bool isOriginalZendThrowExceptionHookSet = false;
static ZendThrowExceptionHook originalZendThrowExceptionHook = NULL;
static bool isLastThrownSet = false;
static zval lastThrown;

void resetLastThrown()
{
    if ( isLastThrownSet ) {
        zval_ptr_dtor( &lastThrown );
        ZVAL_UNDEF( &lastThrown );
        isLastThrownSet = false;
    }
}

void elasticApmZendThrowExceptionHookImpl(
#if PHP_MAJOR_VERSION >= 8 /* if PHP version is 8.* and later */
        zend_object* thrownAsPzobj
#else
        zval* thrownAsPzval
#endif
)
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "isLastThrownSet: %s", boolToString( isLastThrownSet ) );

    resetLastThrown();

#if PHP_MAJOR_VERSION >= 8 /* if PHP version is 8.* and later */
    zval thrownAsZval;
    zval* thrownAsPzval = &thrownAsZval;
    ZVAL_OBJ( /* dst: */ thrownAsPzval, /* src: */ thrownAsPzobj );
#endif
    ZVAL_COPY( /* pZvalDst: */ &lastThrown, /* pZvalSrc: */ thrownAsPzval );

    isLastThrownSet = true;

    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT();
}

void elasticApmZendThrowExceptionHook(
#if PHP_MAJOR_VERSION >= 8 /* if PHP version is 8.* and later */
        zend_object* thrownObj
#else
        zval* thrownObj
#endif
)
{
    elasticApmZendThrowExceptionHookImpl( thrownObj );

    if ( originalZendThrowExceptionHook != NULL )
    {
        originalZendThrowExceptionHook( thrownObj );
    }
}
void setLastThrownIfAnyToTracerPhpPart()
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "isLastThrownSet: %s", boolToString( isLastThrownSet ) );

    ResultCode resultCode;

    if ( isLastThrownSet ) {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( setLastThrownToTracerPhpPart( &lastThrown ) );
    }

    resultCode = resultSuccess;

    finally:

    resetLastThrown();

    ELASTIC_APM_LOG_TRACE_RESULT_CODE_FUNCTION_EXIT();
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    ELASTIC_APM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}

// In PHP 8.1 filename parameter of zend_error_cb() was changed from "const char*" to "zend_string*"
#if PHP_VERSION_ID < 80100
#   define ELASTIC_APM_IS_ZEND_ERROR_CALLBACK_FILE_NAME_C_STRING 1
#else
#   define ELASTIC_APM_IS_ZEND_ERROR_CALLBACK_FILE_NAME_C_STRING 0
#endif

typedef
#       if ELASTIC_APM_IS_ZEND_ERROR_CALLBACK_FILE_NAME_C_STRING == 1
    const char*
#       else
    zend_string*
#       endif
ZendErrorCallbackFileName;

const char* zendErrorCallbackFileNameToCString( ZendErrorCallbackFileName fileName )
{
#       if ELASTIC_APM_IS_ZEND_ERROR_CALLBACK_FILE_NAME_C_STRING == 1
    return fileName;
#       else
    return ZSTR_VAL( fileName );
#       endif
}

// In PHP 8.0
//          zend_error_cb( , const char *format, va_list args )
//  was changed to
//          zend_error_cb( , zend_string* message )
//
#if PHP_MAJOR_VERSION < 8
#   define ELASTIC_APM_IS_ZEND_ERROR_CALLBACK_MSG_VA_LIST 1
#else
#   define ELASTIC_APM_IS_ZEND_ERROR_CALLBACK_MSG_VA_LIST 0
#endif

#if ELASTIC_APM_IS_ZEND_ERROR_CALLBACK_MSG_VA_LIST == 1
#   define ELASTIC_APM_ZEND_ERROR_CALLBACK_SIGNATURE_MSG_PART() const char* messageFormat, va_list messageArgs
#else
#   define ELASTIC_APM_ZEND_ERROR_CALLBACK_SIGNATURE_MSG_PART() zend_string* alreadyFormattedMessage
#endif

#define ELASTIC_APM_ZEND_ERROR_CALLBACK_SIGNATURE() \
    int type \
    , ZendErrorCallbackFileName fileName \
    , const uint32_t lineNumber \
    , ELASTIC_APM_ZEND_ERROR_CALLBACK_SIGNATURE_MSG_PART()

#if ELASTIC_APM_IS_ZEND_ERROR_CALLBACK_MSG_VA_LIST == 1
#   define ELASTIC_APM_ZEND_ERROR_CALLBACK_ARGS_MSG_PART() messageFormat, messageArgs
#else
#   define ELASTIC_APM_ZEND_ERROR_CALLBACK_ARGS_MSG_PART() alreadyFormattedMessage
#endif

#define ELASTIC_APM_ZEND_ERROR_CALLBACK_ARGS() \
    type \
    , fileName \
    , lineNumber \
    , ELASTIC_APM_ZEND_ERROR_CALLBACK_ARGS_MSG_PART()

typedef void (* ZendErrorCallback )( ELASTIC_APM_ZEND_ERROR_CALLBACK_SIGNATURE() );

static bool isOriginalZendErrorCallbackSet = false;
static ZendErrorCallback originalZendErrorCallback = NULL;

void elasticApmZendErrorCallbackImpl( ELASTIC_APM_ZEND_ERROR_CALLBACK_SIGNATURE() )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG(
            "type: %d (%s), fileName: %s, lineNumber: %u"
#               if ELASTIC_APM_IS_ZEND_ERROR_CALLBACK_MSG_VA_LIST == 1
            ", messageFormat: %s"
#               else
            ", alreadyFormattedMessage: %s"
#               endif
            , type, get_php_error_name( type ), zendErrorCallbackFileNameToCString( fileName ), (UInt)lineNumber
#               if ELASTIC_APM_IS_ZEND_ERROR_CALLBACK_MSG_VA_LIST == 1
            , messageFormat
#               else
            , ZSTR_VAL( alreadyFormattedMessage )
#               endif
    );

    ResultCode resultCode;
    char* locallyFormattedMessage = NULL;

#       if ELASTIC_APM_IS_ZEND_ERROR_CALLBACK_MSG_VA_LIST == 1
    va_list messageArgsCopy;
    va_copy( messageArgsCopy, messageArgs );
    // vspprintf allocates memory for the resulted string buffer and it needs to be freed with efree()
    vspprintf( /* out */ &locallyFormattedMessage, 0, messageFormat, messageArgsCopy );
    va_end( messageArgsCopy );
#       endif

    setLastThrownIfAnyToTracerPhpPart();

    ELASTIC_APM_CALL_IF_FAILED_GOTO(
        onPhpErrorToTracerPhpPart(
            type
            , zendErrorCallbackFileNameToCString( fileName )
            , lineNumber
#               if ELASTIC_APM_IS_ZEND_ERROR_CALLBACK_MSG_VA_LIST == 1
            , locallyFormattedMessage
#               else
            , ZSTR_VAL( alreadyFormattedMessage )
#               endif
        )
    );

    resultCode = resultSuccess;

    finally:

#       if ELASTIC_APM_IS_ZEND_ERROR_CALLBACK_MSG_VA_LIST == 0
    if ( locallyFormattedMessage != NULL )
    {
        efree( locallyFormattedMessage );
        locallyFormattedMessage = NULL;
    }
#       endif

    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    return;

    failure:
    goto finally;
}

void elasticApmZendErrorCallback( ELASTIC_APM_ZEND_ERROR_CALLBACK_SIGNATURE() )
{
    elasticApmZendErrorCallbackImpl( ELASTIC_APM_ZEND_ERROR_CALLBACK_ARGS() );

    if ( originalZendErrorCallback != NULL )
    {
        originalZendErrorCallback( ELASTIC_APM_ZEND_ERROR_CALLBACK_ARGS() );
    }
}

void elasticApmRequestInit()
{
#if defined(ZTS) && defined(COMPILE_DL_ELASTIC_APM)
    ZEND_TSRMLS_CACHE_UPDATE();
#endif

    TimePoint requestInitStartTime;
    getCurrentTime( &requestInitStartTime );

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    ResultCode resultCode;
    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* config = getTracerCurrentConfigSnapshot( tracer );

    if ( ! tracer->isInited )
    {
        ELASTIC_APM_LOG_DEBUG( "Extension is not initialized" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if ( tracer->isFailed )
    {
        ELASTIC_APM_LOG_ERROR( "Extension is in failed state" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if ( ! config->enabled )
    {
        ELASTIC_APM_LOG_DEBUG( "Not enabled" );
        resultCode = resultSuccess;
        goto finally;
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( backgroundBackendCommOnRequestInit( config ) );

    if ( isMemoryTrackingEnabled( &tracer->memTracker ) ) memoryTrackerRequestInit( &tracer->memTracker );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( ensureAllComponentsHaveLatestConfig( tracer ) );
    logSupportabilityInfo( logLevel_trace );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( bootstrapTracerPhpPart( config, &requestInitStartTime ) );

//    readSystemMetrics( &tracer->startSystemMetricsReading );

    originalZendErrorCallback = zend_error_cb;
    isOriginalZendErrorCallbackSet = true;
    zend_error_cb = elasticApmZendErrorCallback;
    ELASTIC_APM_LOG_DEBUG( "Set zend_error_cb: %p (%s elasticApmZendErrorCallback) -> %p"
                           , originalZendErrorCallback, originalZendErrorCallback == elasticApmZendErrorCallback ? "==" : "!="
                           , elasticApmZendErrorCallback );

    originalZendThrowExceptionHook = zend_throw_exception_hook;
    isOriginalZendThrowExceptionHookSet = true;
    zend_throw_exception_hook = elasticApmZendThrowExceptionHook;
    ELASTIC_APM_LOG_DEBUG( "Set zend_throw_exception_hook: %p (%s elasticApmZendThrowExceptionHook) -> %p"
                           , originalZendThrowExceptionHook, originalZendThrowExceptionHook == elasticApmZendThrowExceptionHook ? "==" : "!="
                           , elasticApmZendThrowExceptionHook );

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    return;

    failure:
    goto finally;
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
    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* const config = getTracerCurrentConfigSnapshot( tracer );

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    if ( ! tracer->isInited )
    {
        ELASTIC_APM_LOG_TRACE( "Extension is not initialized" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if ( ! config->enabled )
    {
        ELASTIC_APM_LOG_DEBUG( "Extension is not enabled" );
        resultCode = resultSuccess;
        goto finally;
    }

    setLastThrownIfAnyToTracerPhpPart();

    if ( isOriginalZendThrowExceptionHookSet )
    {
        ZendThrowExceptionHook zendThrowExceptionHookBeforeRestore = zend_throw_exception_hook;
        zend_throw_exception_hook = originalZendThrowExceptionHook;
        ELASTIC_APM_LOG_DEBUG( "Restored zend_throw_exception_hook: %p (%s elasticApmZendThrowExceptionHook: %p) -> %p"
                               , zendThrowExceptionHookBeforeRestore, zendThrowExceptionHookBeforeRestore == elasticApmZendThrowExceptionHook ? "==" : "!="
                               , elasticApmZendThrowExceptionHook, originalZendThrowExceptionHook );
        originalZendThrowExceptionHook = NULL;
        isOriginalZendThrowExceptionHookSet = false;
    }

    if ( isOriginalZendErrorCallbackSet )
    {
        ZendErrorCallback zendErrorCallbackBeforeRestore = zend_error_cb;
        zend_error_cb = originalZendErrorCallback;
        ELASTIC_APM_LOG_DEBUG( "Restored zend_error_cb: %p (%s elasticApmZendErrorCallback: %p) -> %p"
                               , zendErrorCallbackBeforeRestore, zendErrorCallbackBeforeRestore == elasticApmZendErrorCallback ? "==" : "!="
                               , elasticApmZendErrorCallback, originalZendErrorCallback );
        originalZendErrorCallback = NULL;
        isOriginalZendErrorCallbackSet = false;
    }

    // We should shutdown PHP part first because sendMetrics() uses metadata sent by PHP part on shutdown
    shutdownTracerPhpPart( config );

    // sendMetrics( tracer, config );

    resetCallInterceptionOnRequestShutdown();

    resultCode = resultSuccess;

    finally:
    if ( tracer->isInited && isMemoryTrackingEnabled( &tracer->memTracker ) )
    {
        memoryTrackerRequestShutdown( &tracer->memTracker );
    }

    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    ELASTIC_APM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}
