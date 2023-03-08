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
#include <zend_builtin_functions.h>
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
    enum { supportInfoBufferSize = 100 * 1000 + 1 };
    char* supportInfoBuffer = NULL;

    ELASTIC_APM_PEMALLOC_STRING_IF_FAILED_GOTO( supportInfoBufferSize, supportInfoBuffer );
    String supportabilityInfo = buildSupportabilityInfo( supportInfoBufferSize, supportInfoBuffer );

    const char* const textEnd = supportabilityInfo + strlen( supportabilityInfo );
    StringView textRemainder = makeStringViewFromBeginEnd( supportabilityInfo, textEnd );
    for ( ;; )
    {
        StringView eolSeq = findEndOfLineSequence( textRemainder );
        if ( isEmptyStringView( eolSeq ) ) break;

        ELASTIC_APM_LOG_WITH_LEVEL( logLevel, "%.*s", (int)( eolSeq.begin - textRemainder.begin), textRemainder.begin );
        textRemainder = makeStringViewFromBeginEnd( stringViewEnd( eolSeq ), textEnd );
    }
    ELASTIC_APM_LOG_WITH_LEVEL( logLevel, "%.*s", (int)( textEnd - textRemainder.begin), textRemainder.begin );

    // resultCode = resultSuccess;

    finally:
    ELASTIC_APM_PEFREE_STRING_SIZE_AND_SET_TO_NULL( supportInfoBufferSize, supportInfoBuffer );
    ELASTIC_APM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}

static pid_t g_pidOnModuleInit = -1;
static pid_t g_pidOnRequestInit = -1;

bool doesCurrentPidMatchPidOnInit( pid_t pidOnInit, String dbgDesc )
{
    pid_t currentPid = getCurrentProcessId();
    if ( pidOnInit != currentPid )
    {
        ELASTIC_APM_LOG_DEBUG( "Process ID on %s init doesn't match the current process ID"
                               " (maybe the current process is a child process forked after the init step?)"
                               "; PID on init: %d, current PID: %d, parent PID: %d"
                               , dbgDesc, (int)pidOnInit, (int)currentPid, (int)(getParentProcessId()) );
        return false;
    }
    return true;
}

void elasticApmModuleInit( int moduleType, int moduleNumber )
{
    registerOsSignalHandler();

    ELASTIC_APM_LOG_DIRECT_DEBUG( "%s entered: moduleType: %d, moduleNumber: %d, parent PID: %d", __FUNCTION__, moduleType, moduleNumber, (int)(getParentProcessId()) );

    g_pidOnModuleInit = getCurrentProcessId();

    ResultCode resultCode;
    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* config = NULL;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( constructTracer( tracer ) );

    if ( ! tracer->isInited )
    {
        ELASTIC_APM_LOG_DEBUG( "Extension is not initialized" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    registerElasticApmIniEntries( moduleType, moduleNumber, &tracer->iniEntriesRegistrationState );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( ensureLoggerInitialConfigIsLatest( tracer ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( ensureAllComponentsHaveLatestConfig( tracer ) );

    logSupportabilityInfo( logLevel_debug );

    config = getTracerCurrentConfigSnapshot( tracer );

    if ( ! config->enabled )
    {
        resultCode = resultSuccess;
        ELASTIC_APM_LOG_DEBUG( "Extension is disabled" );
        goto finally;
    }

    registerCallbacksToLogFork();
    registerAtExitLogging();

    CURLcode curlCode = curl_global_init( CURL_GLOBAL_ALL );
    if ( curlCode != CURLE_OK )
    {
        resultCode = resultFailure;
        ELASTIC_APM_LOG_ERROR( "curl_global_init failed: %s (%d)", curl_easy_strerror( curlCode ), (int)curlCode );
        goto finally;
    }
    tracer->curlInited = true;

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

void elasticApmModuleShutdown( int moduleType, int moduleNumber )
{
    ResultCode resultCode;

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "moduleType: %d, moduleNumber: %d", moduleType, moduleNumber );

    if ( ! doesCurrentPidMatchPidOnInit( g_pidOnModuleInit, "module" ) )
    {
        resultCode = resultSuccess;
        ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT();
        ELASTIC_APM_UNUSED( resultCode );
        return;
    }

    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* const config = getTracerCurrentConfigSnapshot( tracer );

    if ( ! config->enabled )
    {
        resultCode = resultSuccess;
        ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT_MSG( "Because extension is not enabled" );
        goto finally;
    }

    backgroundBackendCommOnModuleShutdown( config );

    if ( tracer->curlInited )
    {
        curl_global_cleanup();
        tracer->curlInited = false;
    }

    unregisterElasticApmIniEntries( moduleType, moduleNumber, &tracer->iniEntriesRegistrationState );

    resultCode = resultSuccess;

    finally:
    destructTracer( tracer );

    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    ELASTIC_APM_UNUSED( resultCode );

    ELASTIC_APM_LOG_DIRECT_DEBUG( "%s exiting...", __FUNCTION__ );
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
static bool g_isLastThrownSet = false;
static zval g_lastThrown;

void resetLastThrown()
{
    if ( ! g_isLastThrownSet )
    {
        return;
    }

    zval_ptr_dtor( &g_lastThrown );
    ZVAL_UNDEF( &g_lastThrown );
    g_isLastThrownSet = false;
}

void elasticApmZendThrowExceptionHookImpl(
#if PHP_MAJOR_VERSION >= 8 /* if PHP version is 8.* and later */
        zend_object* thrownAsPzobj
#else
        zval* thrownAsPzval
#endif
)
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "g_isLastThrownSet: %s", boolToString( g_isLastThrownSet ) );

    resetLastThrown();

#if PHP_MAJOR_VERSION >= 8 /* if PHP version is 8.* and later */
    zval thrownAsZval;
    zval* thrownAsPzval = &thrownAsZval;
    ZVAL_OBJ( /* dst: */ thrownAsPzval, /* src: */ thrownAsPzobj );
#endif
    ZVAL_COPY( /* pZvalDst: */ &g_lastThrown, /* pZvalSrc: */ thrownAsPzval );

    g_isLastThrownSet = true;

    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT();
}

void elasticApmGetLastThrown( zval* return_value )
{
    if ( ! g_isLastThrownSet )
    {
        RETURN_NULL();
    }

    RETURN_ZVAL( &g_lastThrown, /* copy */ true, /* dtor */ false );
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

struct PhpErrorData
{
    int type;
    const char* fileName;
    uint32_t lineNumber;
    const char* message;
    zval stackTrace;
};
typedef struct PhpErrorData PhpErrorData;

static bool g_lastPhpErrorDataSet = false;
static PhpErrorData g_lastPhpErrorData;

void zeroLastPhpErrorData( PhpErrorData* phpErrorData )
{
    phpErrorData->type = -1;
    phpErrorData->fileName = NULL;
    phpErrorData->lineNumber = 0;
    phpErrorData->message = NULL;
    ZVAL_NULL( &( phpErrorData->stackTrace ) );
}

void shallowCopyLastPhpErrorData( PhpErrorData* src, PhpErrorData* dst )
{
    dst->type = src->type;
    dst->fileName = src->fileName;
    dst->lineNumber = src->lineNumber;
    dst->message = src->message;
    dst->stackTrace = src->stackTrace;
}

void freeAndZeroLastPhpErrorData( PhpErrorData* phpErrorData )
{
    if ( phpErrorData->fileName != NULL )
    {
        ELASTIC_APM_EFREE_STRING_AND_SET_TO_NULL( /* in,out */ phpErrorData->fileName );
    }

    if ( phpErrorData->message != NULL )
    {
        ELASTIC_APM_EFREE_STRING_AND_SET_TO_NULL( /* in,out */ phpErrorData->message );
    }

    if ( ! Z_ISNULL( phpErrorData->stackTrace ) )
    {
        zval_ptr_dtor( &( phpErrorData->stackTrace ) );
        ZVAL_NULL( &( phpErrorData->stackTrace ) );
    }

    zeroLastPhpErrorData( phpErrorData );
}

void resetLastPhpErrorData()
{
    if ( ! g_lastPhpErrorDataSet )
    {
        return;
    }

    freeAndZeroLastPhpErrorData( &g_lastPhpErrorData );

    g_lastPhpErrorDataSet = false;
}

void setLastPhpErrorData( int type, const char* fileName, uint32_t lineNumber, const char* message )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "type: %d, fileName: %s, lineNumber: %"PRIu64", message: %s", type, fileName, (UInt64)lineNumber, message );

    ResultCode resultCode;
    PhpErrorData tempPhpErrorData;
    zeroLastPhpErrorData( &tempPhpErrorData );

    if ( fileName != NULL )
    {
        ELASTIC_APM_EMALLOC_DUP_STRING_IF_FAILED_GOTO( fileName, /* out */ tempPhpErrorData.fileName );
    }
    if ( message != NULL )
    {
        ELASTIC_APM_EMALLOC_DUP_STRING_IF_FAILED_GOTO( message, /* out */ tempPhpErrorData.message );
    }

    zend_fetch_debug_backtrace( &( tempPhpErrorData.stackTrace ), /* skip_last */ 0, /* options */ 0, /* limit */ 0 );

    tempPhpErrorData.type = type;
    tempPhpErrorData.lineNumber = lineNumber;

    shallowCopyLastPhpErrorData( &tempPhpErrorData, &g_lastPhpErrorData );
    zeroLastPhpErrorData( &tempPhpErrorData );
    g_lastPhpErrorDataSet = true;

    finally:
    return;

    failure:
    freeAndZeroLastPhpErrorData( &tempPhpErrorData );
    goto finally;
}

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

    setLastPhpErrorData( type, zendErrorCallbackFileNameToCString( fileName ), lineNumber,
#               if ELASTIC_APM_IS_ZEND_ERROR_CALLBACK_MSG_VA_LIST == 1
            locallyFormattedMessage
#               else
            ZSTR_VAL( alreadyFormattedMessage )
#               endif
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

void elasticApmGetLastPhpError( zval* return_value )
{
    if ( ! g_lastPhpErrorDataSet )
    {
        RETURN_NULL();
    }

    array_init( return_value );
    ELASTIC_APM_ZEND_ADD_ASSOC( return_value, "type", long, (zend_long)( g_lastPhpErrorData.type ) );
    ELASTIC_APM_ZEND_ADD_ASSOC_NULLABLE_STRING( return_value, "fileName", g_lastPhpErrorData.fileName );
    ELASTIC_APM_ZEND_ADD_ASSOC( return_value, "lineNumber", long, (zend_long)( g_lastPhpErrorData.lineNumber ) );
    ELASTIC_APM_ZEND_ADD_ASSOC_NULLABLE_STRING( return_value, "message", g_lastPhpErrorData.message );
    Z_TRY_ADDREF( g_lastPhpErrorData.stackTrace );
    ELASTIC_APM_ZEND_ADD_ASSOC( return_value, "stackTrace", zval, &( g_lastPhpErrorData.stackTrace ) );
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
    TimePoint requestInitStartTime;
    getCurrentTime( &requestInitStartTime );

#if defined(ZTS) && defined(COMPILE_DL_ELASTIC_APM)
    ZEND_TSRMLS_CACHE_UPDATE();
#endif

    g_pidOnRequestInit = getCurrentProcessId();

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "parent PID: %d", (int)(getParentProcessId()) );

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

    if ( isMemoryTrackingEnabled( &tracer->memTracker ) ) memoryTrackerRequestInit( &tracer->memTracker );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( ensureAllComponentsHaveLatestConfig( tracer ) );
    logSupportabilityInfo( logLevel_trace );

    if ( config->profilingInferredSpansEnabled ) {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( replaceSleepWithResumingAfterSignalImpl() );
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( bootstrapTracerPhpPart( config, &requestInitStartTime ) );

//    readSystemMetrics( &tracer->startSystemMetricsReading );

    if ( config->captureErrors )
    {
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
    }
    else
    {
        ELASTIC_APM_LOG_DEBUG( "capture_errors (captureErrors) configuration option is set to false which means errors will NOT be captured" );
    }

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
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    ResultCode resultCode;
    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* const config = getTracerCurrentConfigSnapshot( tracer );

    if ( ! doesCurrentPidMatchPidOnInit( g_pidOnRequestInit, "request" ) )
    {
        resultCode = resultSuccess;
        goto finally;
    }

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

    resetLastPhpErrorData();
    resetLastThrown();

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

static pid_t g_lastDetectedCurrentProcessId = -1;

ResultCode resetStateIfForkedChild( String dbgCalledFromFile, int dbgCalledFromLine, String dbgCalledFromFunction )
{
    ResultCode resultCode;
    pid_t lastDetectedCurrentProcessIdSaved;

    if ( g_lastDetectedCurrentProcessId == -1 )
    {
        g_lastDetectedCurrentProcessId = getCurrentProcessId();
        resultCode = resultSuccess;
        goto finally;
    }

    if ( g_lastDetectedCurrentProcessId == getCurrentProcessId() )
    {
        resultCode = resultSuccess;
        goto finally;
    }
    lastDetectedCurrentProcessIdSaved = g_lastDetectedCurrentProcessId;
    g_lastDetectedCurrentProcessId = getCurrentProcessId();

    ELASTIC_APM_CALL_IF_FAILED_GOTO( resetLoggingStateInForkedChild() );
    ELASTIC_APM_LOG_DEBUG( "Detected change in current process ID (PID) - handling it..."
                           "; old PID: %d; parent PID: %d"
                           , (int)lastDetectedCurrentProcessIdSaved, (int)(getParentProcessId()) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( resetBackgroundBackendCommStateInForkedChild() );

    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    goto finally;
}

ResultCode elasticApmEnterAgentCode( String dbgCalledFromFile, int dbgCalledFromLine, String dbgCalledFromFunction )
{
    ResultCode resultCode;

    // We SHOULD NOT log before resetting state if forked because logging might be using thread synchronization
    // which might deadlock in forked child
    ELASTIC_APM_CALL_IF_FAILED_GOTO( resetStateIfForkedChild( dbgCalledFromFile, dbgCalledFromLine, dbgCalledFromFunction ) );

    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    goto finally;
}