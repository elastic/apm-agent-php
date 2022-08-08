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
#include <zend_hash.h>
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
#include "numbered_intercepting_callbacks.h"

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
    ELASTIC_APM_LOG_WITH_LEVEL( logLevel, "Version of agent C part: %s", PHP_ELASTIC_APM_FULL_VERSION );

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

static int g_currentReentrancyDepth = 0;
enum { maxReentrancyDepth = 3 };

bool validateReentrancyState()
{
    if ( g_currentReentrancyDepth < 0 )
    {
        ELASTIC_APM_LOG_CRITICAL( "g_currentReentrancyDepth (%d) should not be greater than maxReentrancyDepth (%d)", g_currentReentrancyDepth, maxReentrancyDepth );
        return false;
    }

    if ( g_currentReentrancyDepth > maxReentrancyDepth )
    {
        ELASTIC_APM_LOG_CRITICAL( "g_currentReentrancyDepth (%d) should not be greater than maxReentrancyDepth (%d)", g_currentReentrancyDepth, maxReentrancyDepth );
        return false;
    }

    return true;
}

bool tryToEnterElasticApmCode()
{
    if ( ! validateReentrancyState() )
    {
        return false;
    }

    if ( g_currentReentrancyDepth == maxReentrancyDepth )
    {
        ELASTIC_APM_LOG_WARNING( "Reached max reentrancy depth (%d)", maxReentrancyDepth );
        return false;
    }

    ++g_currentReentrancyDepth;
    return true;
}

void exitedElasticApmCode()
{
    if ( ! validateReentrancyState() )
    {
        return;
    }

    if ( g_currentReentrancyDepth == 0 )
    {
        ELASTIC_APM_LOG_CRITICAL( "g_currentReentrancyDepth should not be 0 when exiting Elastic APM code (maxReentrancyDepth: %d)", maxReentrancyDepth );
        return;
    }

    --g_currentReentrancyDepth;
}
static pid_t g_pidOnRequestInit = -1;

bool doesCurrentPidMatchPidOnRequestInit()
{
    pid_t currentPid = getCurrentProcessId();
    if ( g_pidOnRequestInit != currentPid )
    {
        ELASTIC_APM_LOG_DEBUG( "Process ID on request init doesn't match the current process ID"
                               " (maybe the current process is a child process forked after the request started?)"
                               "; g_pidOnRequestInit: %d, currentPid: %d"
                               , (int)g_pidOnRequestInit, (int)currentPid );
        return false;
    }
    return true;
}

//typedef void (* AstProcessCallback )( zend_ast* ast );
//static bool originalAstProcessCallbackSet = false;
//static AstProcessCallback originalAstProcessCallback;
//static bool bootstrapTracerPhpPartCalled = false;

static TimePoint g_requestInitStartTime;

//void elasticApmAstProcessCallback( zend_ast* ast )
//{
//    if ( bootstrapTracerPhpPartCalled )
//    {
//        return;
//    }
//
//    Tracer* const tracer = getGlobalTracer();
//    const ConfigSnapshot* config = getTracerCurrentConfigSnapshot( tracer );
//    ResultCode bootstrapResultCode = bootstrapTracerPhpPart( config, &g_requestInitStartTime );
//    bootstrapTracerPhpPartCalled = true;
//    ELASTIC_APM_LOG_WITH_LEVEL( bootstrapResultCode == resultSuccess ? logLevel_debug : logLevel_error, "bootstrapResultCode: %s (%d)", resultCodeToString( bootstrapResultCode ), bootstrapResultCode );
//
//    /* call the original zend_ast_process function if one was set */
//    if ( originalAstProcessCallbackSet ) {
//        originalAstProcessCallback( ast );
//    }
//}

void elasticApmModuleInit( int type, int moduleNumber )
{
    ELASTIC_APM_UNUSED( type );

    ResultCode resultCode;
    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* config = NULL;

    registerOsSignalHandler();

    ELASTIC_APM_CALL_IF_FAILED_GOTO( constructTracer( tracer ) );

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

//    originalAstProcessCallback = zend_ast_process;
//    originalAstProcessCallbackSet = true;
//    zend_ast_process = elasticApmAstProcessCallback;

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

    if ( ! doesCurrentPidMatchPidOnRequestInit() )
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

static bool g_isEnabledForCurrentRequest = true;
static const char* g_isEnabledForCurrentRequestReason = "Not checked yet";

#define ELASTIC_APM_OPCACHE_GET_STATUS_FUNC_NAME "opcache_get_status"
#define ELASTIC_APM_OPCACHE_GET_STATUS_FUNC_NAME_LEN (ELASTIC_APM_STATIC_ARRAY_SIZE( ELASTIC_APM_OPCACHE_GET_STATUS_FUNC_NAME ) - 1)

#define ELASTIC_APM_OPCACHE_GET_STATUS_RESTART_PENDING_KEY "restart_pending"
#define ELASTIC_APM_OPCACHE_GET_STATUS_RESTART_PENDING_KEY_LEN (ELASTIC_APM_STATIC_ARRAY_SIZE( ELASTIC_APM_OPCACHE_GET_STATUS_RESTART_PENDING_KEY ) - 1)
#define ELASTIC_APM_OPCACHE_GET_STATUS_RESTART_IN_PROGRESS_KEY "restart_in_progress"
#define ELASTIC_APM_OPCACHE_GET_STATUS_RESTART_IN_PROGRESS_KEY_LEN (ELASTIC_APM_STATIC_ARRAY_SIZE( ELASTIC_APM_OPCACHE_GET_STATUS_RESTART_IN_PROGRESS_KEY ) - 1)

zend_function* getOpcacheGetStatusFuncEntry()
{
    return zend_hash_str_find_ptr( CG( function_table ), ELASTIC_APM_OPCACHE_GET_STATUS_FUNC_NAME, ELASTIC_APM_OPCACHE_GET_STATUS_FUNC_NAME_LEN );
}

bool isOpCacheEnabled()
{
    return getOpcacheGetStatusFuncEntry() != NULL;
}

ResultCode findBoolInZvalArray( zval* zvalArray, const char* key, size_t keyLen, bool* val )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    ResultCode resultCode;
    zval* pFoundZval = NULL;

    pFoundZval = zend_hash_str_find( Z_ARRVAL_P( zvalArray ), key, keyLen );
    if ( pFoundZval == NULL)
    {
        ELASTIC_APM_LOG_ERROR( "Could not find value by key: %.*s", (int)keyLen, key );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if ( Z_TYPE_P( pFoundZval ) == IS_FALSE )
    {
        *val = false;
    }
    else if ( Z_TYPE_P( pFoundZval ) == IS_TRUE )
    {
        *val = true;
    }
    else
    {
        ELASTIC_APM_LOG_ERROR( "Found value is not of type bool. Actual type: %s; key: %.*s", zend_get_type_by_const( Z_TYPE_P( pFoundZval ) ), (int)keyLen, key );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    ELASTIC_APM_LOG_DEBUG( "Found value is %s (key: %.*s)", boolToString( *val ), (int)keyLen, key );
    resultCode = resultSuccess;

    finally:

    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    goto finally;
}

ResultCode getOpCacheStatus( bool* restartPending, bool* restartInProgress )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    ResultCode resultCode;
    zval include_scripts_param;
    ZVAL_BOOL( &include_scripts_param, false );
    zval params[] = { include_scripts_param };
    zval opcacheGetStatusRetVal;
    ZVAL_UNDEF( &opcacheGetStatusRetVal );
    int opcacheGetStatusCallRetVal = SUCCESS;
    zval opcacheGetStatusFuncFuncName;
    ZVAL_UNDEF( &opcacheGetStatusFuncFuncName );

    ZVAL_STRINGL( &opcacheGetStatusFuncFuncName, ELASTIC_APM_OPCACHE_GET_STATUS_FUNC_NAME, ELASTIC_APM_OPCACHE_GET_STATUS_FUNC_NAME_LEN );
    opcacheGetStatusCallRetVal = call_user_function( CG(function_table), /* object */ NULL, &opcacheGetStatusFuncFuncName, &opcacheGetStatusRetVal, ELASTIC_APM_STATIC_ARRAY_SIZE( params ), params );
    if ( opcacheGetStatusCallRetVal != SUCCESS )
    {
        ELASTIC_APM_LOG_ERROR( "call_user_function failed. Return value: %d", opcacheGetStatusCallRetVal );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if ( Z_TYPE( opcacheGetStatusRetVal ) != IS_ARRAY )
    {
        ELASTIC_APM_LOG_ERROR( "opcache_get_status return value is not an array."
                               " zend_get_type_by_const( Z_TYPE( opcacheGetStatusRetVal ) ): %s"
                               , zend_get_type_by_const( Z_TYPE( opcacheGetStatusRetVal ) ) );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( findBoolInZvalArray( &opcacheGetStatusRetVal, ELASTIC_APM_OPCACHE_GET_STATUS_RESTART_PENDING_KEY, ELASTIC_APM_OPCACHE_GET_STATUS_RESTART_PENDING_KEY_LEN, restartPending ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( findBoolInZvalArray( &opcacheGetStatusRetVal, ELASTIC_APM_OPCACHE_GET_STATUS_RESTART_IN_PROGRESS_KEY, ELASTIC_APM_OPCACHE_GET_STATUS_RESTART_IN_PROGRESS_KEY_LEN, restartInProgress ) );

    resultCode = resultSuccess;

    finally:

    zval_dtor( &opcacheGetStatusRetVal );
    zval_dtor( &opcacheGetStatusFuncFuncName );

    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    goto finally;
}

bool checkIfEnabledForCurrentRequest( const char* calledFromFunction )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "Check called from %s; g_isEnabledForCurrentRequest: %s", calledFromFunction, boolToString( g_isEnabledForCurrentRequest ) );

    if ( ! g_isEnabledForCurrentRequest ) {
        goto finally;
    }

    ResultCode resultCode;
    bool restartPending = false;
    bool restartInProgress = false;

    if ( isOpCacheEnabled() )
    {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( getOpCacheStatus( &restartPending, &restartInProgress ) );
        if ( restartPending )
        {
            g_isEnabledForCurrentRequest = false;
            g_isEnabledForCurrentRequestReason = "opcache_get_status()['" ELASTIC_APM_OPCACHE_GET_STATUS_RESTART_PENDING_KEY "'] is true";
            goto finally;
        }
        if ( restartInProgress )
        {
            g_isEnabledForCurrentRequest = false;
            g_isEnabledForCurrentRequestReason = "opcache_get_status()['" ELASTIC_APM_OPCACHE_GET_STATUS_RESTART_IN_PROGRESS_KEY "'] is true";
            goto finally;
        }
    }

    g_isEnabledForCurrentRequest = true;
    g_isEnabledForCurrentRequestReason = "Passed all checks";

    finally:

    ELASTIC_APM_LOG_DEBUG( "Elastic APM is %s for the current request. Reason: %s. Check called from %s"
                           , (g_isEnabledForCurrentRequest ? "enabled" : "DISABLED")
                           , g_isEnabledForCurrentRequestReason
                           , calledFromFunction );
    return g_isEnabledForCurrentRequest;

    failure:
    ELASTIC_APM_LOG_DEBUG( "Failed to check if enabled for current request - defaulting to DISABLED" );
    g_isEnabledForCurrentRequest = false;
    g_isEnabledForCurrentRequestReason = "Failed to check";
    goto finally;
}

bool resetIfEnabledForCurrentRequest()
{
    g_isEnabledForCurrentRequest = true;
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
// TODO: Sergey Kleyman: Uncomment or Remove
//    if ( ! checkIfEnabledForCurrentRequest( __FUNCTION__ ) )
    if ( ! g_isEnabledForCurrentRequest )
    {
        return;
    }

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
    if ( tryToEnterElasticApmCode() )
    {
        elasticApmZendThrowExceptionHookImpl( thrownObj );
        exitedElasticApmCode();
    }

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
// TODO: Sergey Kleyman: Uncomment or Remove
//    if ( ! checkIfEnabledForCurrentRequest( __FUNCTION__ ) )
    if ( ! g_isEnabledForCurrentRequest )
    {
        return;
    }

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
    if ( tryToEnterElasticApmCode() )
    {
        elasticApmZendErrorCallbackImpl( ELASTIC_APM_ZEND_ERROR_CALLBACK_ARGS() );
        exitedElasticApmCode();
    }

    if ( originalZendErrorCallback != NULL )
    {
        originalZendErrorCallback( ELASTIC_APM_ZEND_ERROR_CALLBACK_ARGS() );
    }
}

enum { maxFunctionsToIntercept = 100 };
static uint32_t g_nextFreeFunctionToInterceptId = 0;
static CallToInterceptData g_functionsToInterceptData[maxFunctionsToIntercept];
static bool g_interceptedCallInProgress = false;

void unregisterCallbacksToInvokeBootstrapTracerPhpPart();

void internalFunctionCallInterceptingImpl_bootstrapTracerPhpPart( uint32_t interceptRegistrationId, zend_execute_data* execute_data, zval* return_value )
{
    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY_MSG( "interceptRegistrationId: %u", interceptRegistrationId );

    if ( g_interceptedCallInProgress )
    {
        ELASTIC_APM_LOG_TRACE( "There's already an intercepted call in progress");
    }
    else
    {
        g_interceptedCallInProgress = true;
        unregisterCallbacksToInvokeBootstrapTracerPhpPart();
        Tracer* const tracer = getGlobalTracer();
        const ConfigSnapshot* config = getTracerCurrentConfigSnapshot( tracer );
        ResultCode bootstrapResultCode = bootstrapTracerPhpPart( config, &g_requestInitStartTime );
        ELASTIC_APM_LOG_WITH_LEVEL( bootstrapResultCode == resultSuccess ? logLevel_debug : logLevel_error, "bootstrapResultCode: %s (%d)", resultCodeToString( bootstrapResultCode ), bootstrapResultCode );
        g_interceptedCallInProgress = false;
    }

    g_functionsToInterceptData[ interceptRegistrationId ].originalHandler( execute_data, return_value );

    ELASTIC_APM_LOG_TRACE_FUNCTION_EXIT_MSG( "interceptRegistrationId: %u", interceptRegistrationId );
}

static
void internalFunctionCallInterceptingImpl( uint32_t interceptRegistrationId, zend_execute_data* execute_data, zval* return_value )
{
    internalFunctionCallInterceptingImpl_bootstrapTracerPhpPart( interceptRegistrationId, execute_data, return_value );
}

ResultCode addCallbacksToInvokeBootstrapTracerPhpPart( String className, String functionName )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "className: %s, functionName: %s", (className == NULL ? "N/A" : className), functionName );

    ResultCode resultCode;
    zend_function* funcEntry = NULL;

    if ( g_nextFreeFunctionToInterceptId >= maxFunctionsToIntercept )
    {
        ELASTIC_APM_LOG_ERROR( "Reached maxFunctionsToIntercept."
                               " maxFunctionsToIntercept: %u. g_nextFreeFunctionToInterceptId: %u."
                               , maxFunctionsToIntercept, g_nextFreeFunctionToInterceptId );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if ( className == NULL )
    {
        funcEntry = zend_hash_str_find_ptr( EG( function_table ), functionName, strlen( functionName ) );
        if ( funcEntry == NULL )
        {
            ELASTIC_APM_LOG_ERROR( "zend_hash_str_find_ptr( EG( function_table ), ... ) failed."
                                   " functionName: `%s'", functionName );
            ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
        }
    }
    else
    {
        zend_class_entry* classEntry = zend_hash_str_find_ptr( CG( class_table ), className, strlen( className ) );
        if ( classEntry == NULL )
        {
            ELASTIC_APM_LOG_ERROR( "zend_hash_str_find_ptr( CG( class_table ), ... ) failed. className: `%s'", className );
            ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
        }

        funcEntry = zend_hash_str_find_ptr( &classEntry->function_table, functionName, strlen( functionName ) );
        if ( funcEntry == NULL )
        {
            ELASTIC_APM_LOG_ERROR( "zend_hash_str_find_ptr( &classEntry->function_table, ... ) failed."
                                   " className: `%s'; functionName: `%s'", className, functionName );
            ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
        }
    }

    uint32_t interceptRegistrationId = g_nextFreeFunctionToInterceptId ++;
    g_functionsToInterceptData[ interceptRegistrationId ].funcEntry = funcEntry;
    g_functionsToInterceptData[ interceptRegistrationId ].originalHandler = funcEntry->internal_function.handler;
    funcEntry->internal_function.handler = g_numberedInterceptingCallback[ interceptRegistrationId ];

    resultCode = resultSuccess;

    finally:

    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT_MSG( "interceptRegistrationId: %u", interceptRegistrationId );
    return resultCode;

    failure:
    goto finally;
}

void registerCallbacksToInvokeBootstrapTracerPhpPart()
{
    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY();

    addCallbacksToInvokeBootstrapTracerPhpPart( /* className: */ NULL, "curl_init" );
    /* className must be in lower case */
    addCallbacksToInvokeBootstrapTracerPhpPart( /* className: */ "pdo", "__construct" );

    ELASTIC_APM_LOG_TRACE_FUNCTION_EXIT();
}

void unregisterCallbacksToInvokeBootstrapTracerPhpPart()
{
    // We restore original handlers in the reverse order
    // so that if the same function is registered for interception more than once
    // the original handler will be restored correctly
    ELASTIC_APM_FOR_EACH_BACKWARDS( i, g_nextFreeFunctionToInterceptId )
    {
        CallToInterceptData* data = &( g_functionsToInterceptData[ i ] );

        data->funcEntry->internal_function.handler = data->originalHandler;
    }

    g_nextFreeFunctionToInterceptId = 0;
}

void elasticApmRequestInit()
{
#if defined(ZTS) && defined(COMPILE_DL_ELASTIC_APM)
    ZEND_TSRMLS_CACHE_UPDATE();
#endif

    resetIfEnabledForCurrentRequest();
    // TODO: Sergey Kleyman: Uncomment checkIfEnabledForCurrentRequest in elasticApmRequestInit
//    if ( ! checkIfEnabledForCurrentRequest( __FUNCTION__ ) ) {
//        return;
//    }

    g_pidOnRequestInit = getCurrentProcessId();

    // TimePoint requestInitStartTime;
    getCurrentTime( &g_requestInitStartTime );

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "Elastic APM PHP Agent version: %s", PHP_ELASTIC_APM_FULL_VERSION );

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

    // ELASTIC_APM_CALL_IF_FAILED_GOTO( bootstrapTracerPhpPart( config, &requestInitStartTime ) );
    registerCallbacksToInvokeBootstrapTracerPhpPart();

//    readSystemMetrics( &tracer->startSystemMetricsReading );

    // TODO: Sergey Kleyman: Uncomment
//    originalZendErrorCallback = zend_error_cb;
//    isOriginalZendErrorCallbackSet = true;
//    zend_error_cb = elasticApmZendErrorCallback;
//    ELASTIC_APM_LOG_DEBUG( "Set zend_error_cb: %p (%s elasticApmZendErrorCallback) -> %p"
//                           , originalZendErrorCallback, originalZendErrorCallback == elasticApmZendErrorCallback ? "==" : "!="
//                           , elasticApmZendErrorCallback );

    // TODO: Sergey Kleyman: Uncomment
//    originalZendThrowExceptionHook = zend_throw_exception_hook;
//    isOriginalZendThrowExceptionHookSet = true;
//    zend_throw_exception_hook = elasticApmZendThrowExceptionHook;
//    ELASTIC_APM_LOG_DEBUG( "Set zend_throw_exception_hook: %p (%s elasticApmZendThrowExceptionHook) -> %p"
//                           , originalZendThrowExceptionHook, originalZendThrowExceptionHook == elasticApmZendThrowExceptionHook ? "==" : "!="
//                           , elasticApmZendThrowExceptionHook );

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
    // TODO: Sergey Kleyman: Uncomment checkIfEnabledForCurrentRequest in elasticApmRequestShutdown
//    if ( ! checkIfEnabledForCurrentRequest( __FUNCTION__ ) ) {
//        return;
//    }

    ResultCode resultCode;
    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* const config = getTracerCurrentConfigSnapshot( tracer );

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    if ( ! doesCurrentPidMatchPidOnRequestInit() )
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

    ELASTIC_APM_CALL_IF_FAILED_GOTO( bootstrapTracerPhpPart( config, &g_requestInitStartTime ) );

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
    unregisterCallbacksToInvokeBootstrapTracerPhpPart();

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

void elasticApmForceBootstrapPhpPart()
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "timePointToEpochMicroseconds( &g_requestInitStartTime ): %"PRIu64, timePointToEpochMicroseconds( &g_requestInitStartTime ) );

    ResultCode resultCode;
    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* const config = getTracerCurrentConfigSnapshot( tracer );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( bootstrapTracerPhpPart( config, &g_requestInitStartTime ) );

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return;

    failure:
    goto finally;
}
