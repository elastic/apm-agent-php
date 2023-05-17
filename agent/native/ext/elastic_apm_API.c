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

#include "elastic_apm_API.h"
#include <unistd.h>
#include <php.h>
#include "util.h"
#include "log.h"
#include "Tracer.h"
#include "elastic_apm_alloc.h"
#include "numbered_intercepting_callbacks.h"
#include "tracer_PHP_part.h"
#include "backend_comm.h"
#include "lifecycle.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_EXT_API

ResultCode elasticApmApiEntered( String dbgCalledFromFile, int dbgCalledFromLine, String dbgCalledFromFunction )
{
    // We SHOULD NOT log before resetting state if forked because logging might be using thread synchronization
    // which might deadlock in forked child
    return elasticApmEnterAgentCode( dbgCalledFromFile, dbgCalledFromLine, dbgCalledFromFunction );
}

bool elasticApmIsEnabled()
{
    const bool result = getTracerCurrentConfigSnapshot( getGlobalTracer() )->enabled;

    ELASTIC_APM_LOG_TRACE( "Result: %s", boolToString( result ) );
    return result;
}

ResultCode elasticApmGetConfigOption( String optionName, zval* return_value )
{
    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY_MSG( "optionName: `%s'", optionName );

    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    GetConfigManagerOptionValueByNameResult getOptValueByNameRes;
    getOptValueByNameRes.txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    getOptValueByNameRes.streamedParsedValue = NULL;
    const ResultCode resultCode = getConfigManagerOptionValueByName(
            getGlobalTracer()->configManager
            , optionName
            , &getOptValueByNameRes );
    if ( resultCode == resultSuccess ) *return_value = getOptValueByNameRes.parsedValueAsZval;

    ELASTIC_APM_LOG_TRACE_RESULT_CODE_FUNCTION_EXIT_MSG( "Option's: name: `%s', value: %s", optionName, getOptValueByNameRes.streamedParsedValue );
    return resultCode;
}

UInt elasticApmGetNumberOfDynamicConfigOptions()
{
    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY();

    const ConfigManager* const cfgManager = getGlobalTracer()->configManager;

    UInt result = 0;
    ELASTIC_APM_FOR_EACH_OPTION_ID( optId )
    {
        GetConfigManagerOptionMetadataResult getMetaRes;
        getConfigManagerOptionMetadata( cfgManager, optId, &getMetaRes );
        if ( getMetaRes.isDynamic ) ++result;
    }

    ELASTIC_APM_LOG_TRACE_FUNCTION_EXIT_MSG( "result: %d", result );
    return result;
}

enum { maxFunctionsToIntercept = numberedInterceptingCallbacksCount };
static uint32_t g_nextFreeFunctionToInterceptId = 0;
struct CallToInterceptData
{
    zif_handler originalHandler;
    zend_function* funcEntry;
};
typedef struct CallToInterceptData CallToInterceptData;
static CallToInterceptData g_functionsToInterceptData[maxFunctionsToIntercept];

static uint32_t g_interceptedCallInProgressRegistrationId = 0;

static
void internalFunctionCallInterceptingImpl( uint32_t interceptRegistrationId, zend_execute_data* execute_data, zval* return_value )
{
    ResultCode resultCode;

    // We SHOULD NOT log before resetting state if forked because logging might be using thread synchronization
    // which might deadlock in forked child
    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmEnterAgentCode( __FILE__, __LINE__, __FUNCTION__ ) );

    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY_MSG( "interceptRegistrationId: %u", interceptRegistrationId );

    bool shouldCallPostHook;

    if ( g_interceptedCallInProgressRegistrationId != 0 )
    {
        ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY_MSG(
                "There's already an intercepted call in progress with interceptRegistrationId: %u."
                "Nesting intercepted calls is not supported yet so invoking the original handler directly..."
                , g_interceptedCallInProgressRegistrationId );
        g_functionsToInterceptData[ interceptRegistrationId ].originalHandler( execute_data, return_value );
        return;
    }

    g_interceptedCallInProgressRegistrationId = interceptRegistrationId;

    shouldCallPostHook = tracerPhpPartInterceptedCallPreHook( interceptRegistrationId, execute_data );
    g_functionsToInterceptData[ interceptRegistrationId ].originalHandler( execute_data, return_value );
    if ( shouldCallPostHook ) {
        tracerPhpPartInterceptedCallPostHook( interceptRegistrationId, return_value );
    }

    g_interceptedCallInProgressRegistrationId = 0;

    ELASTIC_APM_LOG_TRACE_FUNCTION_EXIT_MSG( "interceptRegistrationId: %u", interceptRegistrationId );
    resultCode = resultSuccess;
    finally:
    return;

    failure:
    goto finally;
}

void resetCallInterceptionOnRequestShutdown()
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

bool addToFunctionsToInterceptData( zend_function* funcEntry, uint32_t* interceptRegistrationId, zif_handler replacementFunc )
{
    if ( g_nextFreeFunctionToInterceptId >= maxFunctionsToIntercept )
    {
        ELASTIC_APM_LOG_ERROR( "Reached maxFunctionsToIntercept."
                               " maxFunctionsToIntercept: %u. g_nextFreeFunctionToInterceptId: %u."
                               , maxFunctionsToIntercept, g_nextFreeFunctionToInterceptId );
        return false;
    }

    *interceptRegistrationId = g_nextFreeFunctionToInterceptId ++;
    g_functionsToInterceptData[ *interceptRegistrationId ].funcEntry = funcEntry;
    g_functionsToInterceptData[ *interceptRegistrationId ].originalHandler = funcEntry->internal_function.handler;
    funcEntry->internal_function.handler = ( replacementFunc == NULL ) ? g_numberedInterceptingCallback[ *interceptRegistrationId ] : replacementFunc;

    return true;
}

ResultCode elasticApmInterceptCallsToInternalMethod( String className, String methodName, uint32_t* interceptRegistrationId )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "className: `%s'; methodName: `%s'", className, methodName );

    ResultCode resultCode;
    zend_class_entry* classEntry = NULL;
    zend_function* funcEntry = NULL;

    classEntry = zend_hash_str_find_ptr( CG( class_table ), className, strlen( className ) );
    if ( classEntry == NULL )
    {
        ELASTIC_APM_LOG_ERROR( "zend_hash_str_find_ptr( CG( class_table ), ... ) failed. className: `%s'", className );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    funcEntry = zend_hash_str_find_ptr( &classEntry->function_table, methodName, strlen( methodName ) );
    if ( funcEntry == NULL )
    {
        ELASTIC_APM_LOG_ERROR( "zend_hash_str_find_ptr( &classEntry->function_table, ... ) failed."
                               " className: `%s'; methodName: `%s'", className, methodName );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if ( ! addToFunctionsToInterceptData( funcEntry, interceptRegistrationId, /* replacementFunc */ NULL) )
    {
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    resultCode = resultSuccess;

    finally:

    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    goto finally;
}

ResultCode elasticApmInterceptCallsToInternalFunctionEx( String functionName, uint32_t* interceptRegistrationId, zif_handler replacementFunc )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "functionName: `%s'", functionName );

    ResultCode resultCode;

    zend_function* funcEntry = zend_hash_str_find_ptr( CG( function_table ), functionName, strlen( functionName ) );
    if ( funcEntry == NULL )
    {
        ELASTIC_APM_LOG_ERROR( "zend_hash_str_find_ptr( CG( function_table ), ... ) failed."
                               " functionName: `%s'", functionName );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if ( ! addToFunctionsToInterceptData( funcEntry, interceptRegistrationId, replacementFunc ) )
    {
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    resultCode = resultSuccess;

    finally:

    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT_MSG( "interceptRegistrationId: %u", *interceptRegistrationId );
    return resultCode;

    failure:
    goto finally;
}

ResultCode elasticApmInterceptCallsToInternalFunction( String functionName, uint32_t* interceptRegistrationId)
{
    return elasticApmInterceptCallsToInternalFunctionEx( functionName, interceptRegistrationId, /* replacementFunc */ NULL );
}

static inline bool longToBool( long longVal )
{
    return longVal != 0;
}

ResultCode elasticApmSendToServer( StringView userAgentHttpHeader, StringView serializedEvents )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    ResultCode resultCode;
    Tracer* const tracer = getGlobalTracer();

    ELASTIC_APM_CALL_IF_FAILED_GOTO( sendEventsToApmServer( getTracerCurrentConfigSnapshot( tracer ), userAgentHttpHeader, serializedEvents ) );

    resultCode = resultSuccess;
    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    goto finally;
}

void sleepUntilImpl( TimeVal endTime )
{
#   if HAVE_NANOSLEEP

    TimeVal currentTime;
    TimeVal timeDiff;
    struct timespec timespecToSleep;
    struct timespec timespecRemaining;

    if ( gettimeofday( /* out */ &currentTime, /* timezone */ NULL) != 0 )
    {
        return;
    }

    timeDiff = calcTimeValDiff( currentTime, endTime );
    timespecToSleep.tv_sec = timeDiff.tv_sec;
    timespecToSleep.tv_nsec = timeDiff.tv_usec * 1000;

    while ( true )
    {
        tracerPhpPartInterceptedCallEmptyMethod();
        if (nanosleep( &timespecToSleep, &timespecRemaining ) == 0 )
        {
            return;
        }
        if ( errno != EINTR )
        {
            return;
        }
        timespecToSleep.tv_sec = timespecRemaining.tv_sec;
        timespecToSleep.tv_nsec = timespecRemaining.tv_nsec;
    }

#   else // #if HAVE_NANOSLEEP

    while ( true )
    {
        TimeVal currentTime;
        TimeVal timeDiff;
        if ( gettimeofday( /* out */ &currentTime, /* timezone */ NULL) != 0 )
        {
            return;
        }
        timeDiff = calcTimeValDiff( currentTime, endTime );
        if ( timeDiff.tv_sec < 0 || timeDiff.tv_usec < 0 )
        {
            break;
        }
        if ( timeDiff.tv_sec > 0 )
        {
            tracerPhpPartInterceptedCallEmptyMethod();
            php_sleep( timeDiff.tv_sec );
        }
        else if ( timeDiff.tv_usec > 0 )
        {
#           if HAVE_USLEEP
            tracerPhpPartInterceptedCallEmptyMethod();
            usleep( (useconds_t)(timeDiff.tv_usec) );
#           endif // #if HAVE_USLEEP
        }
        else
        {
            break;
        }
    }
#   endif // #if HAVE_NANOSLEEP
}

enum SleepFuncRetVal
{
    sleepFuncRetVal_success,
    sleepFuncRetVal_interrupted,
    sleepFuncRetVal_failure,
    sleepFuncRetVal_void
};
typedef enum SleepFuncRetVal SleepFuncRetVal;

SleepFuncRetVal sleep_parseRetVal( const zval* return_value )
{

#   if PHP_VERSION_ID >= 80000 || defined( PHP_SLEEP_NON_VOID )

    if ( Z_TYPE_P( return_value ) != IS_LONG )
    {
        return sleepFuncRetVal_failure;
    }

    zend_long retValAsLong = Z_LVAL_P( return_value );
    return retValAsLong == 0 ? sleepFuncRetVal_success : sleepFuncRetVal_interrupted;

#   else // if PHP_VERSION_ID >= 80000 || defined( PHP_SLEEP_NON_VOID )

    return sleepFuncRetVal_void;

#   endif // if PHP_VERSION_ID >= 80000 || defined( PHP_SLEEP_NON_VOID )
}

void sleep_setSuccessRetVal( const zval* retValCopyBeforeCallToOriginalFunc, zval* return_value )
{
#   if PHP_VERSION_ID >= 80000 || defined( PHP_SLEEP_NON_VOID )

    RETURN_LONG( 0 );

#   else // if PHP_VERSION_ID >= 80000 || defined( PHP_SLEEP_NON_VOID )

    *return_value = *retValCopyBeforeCallToOriginalFunc;

#   endif // if PHP_VERSION_ID >= 80000 || defined( PHP_SLEEP_NON_VOID )
}

SleepFuncRetVal usleep_parseRetVal( const zval* retVal )
{
    return sleepFuncRetVal_void;
}

void usleep_setSuccessRetVal( const zval* retValCopyBeforeCallToOriginalFunc, zval* return_value )
{
    *return_value = *retValCopyBeforeCallToOriginalFunc;
}

SleepFuncRetVal time_nanosleep_parseRetVal( const zval* return_value )
{
    if ( Z_TYPE_P( return_value ) == IS_FALSE )
    {
        return sleepFuncRetVal_failure;
    }

    if ( Z_TYPE_P( return_value ) == IS_TRUE )
    {
        return sleepFuncRetVal_success;
    }

    if ( Z_TYPE_P( return_value ) == IS_ARRAY )
    {
        return sleepFuncRetVal_interrupted;
    }

    return sleepFuncRetVal_failure;
}

void time_nanosleep_setSuccessRetVal( __attribute__((unused)) const zval* retValCopyBeforeCallToOriginalFunc, zval* return_value )
{
    RETURN_TRUE;
}

typedef SleepFuncRetVal (* SleepFuncRetValParser )( const zval* return_value );
typedef void (* SleepSetSuccessRetValFunc )( const zval* retValCopyBeforeCallToOriginalFunc, zval* return_value );

void sleepResumingAfterInterruption(
        zend_long seconds
        , zend_long nanoSeconds
        , zif_handler originalFunc
        , SleepFuncRetValParser parseSleepFuncRetVal
        , SleepSetSuccessRetValFunc setSuccessRetValFunc
        , INTERNAL_FUNCTION_PARAMETERS )
{
    TimeVal beginTime;

    if ( seconds == 0 && nanoSeconds == 0)
    {
        originalFunc( INTERNAL_FUNCTION_PARAM_PASSTHRU );
        return;
    }

    if ( gettimeofday( /* out */ &beginTime, /* timezone */ NULL) != 0 )
    {
        originalFunc( INTERNAL_FUNCTION_PARAM_PASSTHRU );
        return;
    }

    bool wasExceptionInProgress = ( EG( exception ) != NULL );
    const zval retValCopyBeforeCallToOriginalFunc = *return_value;
    originalFunc( INTERNAL_FUNCTION_PARAM_PASSTHRU );
    if ( !wasExceptionInProgress && ( EG( exception ) != NULL ) )
    {
        return;
    }

    SleepFuncRetVal originalFuncRetVal = parseSleepFuncRetVal( return_value );
    if ( originalFuncRetVal != sleepFuncRetVal_interrupted && originalFuncRetVal != sleepFuncRetVal_void )
    {
        return;
    }
    sleepUntilImpl( calcEndTimeVal( beginTime, seconds, nanoSeconds ) );
    setSuccessRetValFunc( &retValCopyBeforeCallToOriginalFunc, return_value );
}

static uint32_t sleep_resuming_after_interruption_interceptRegistrationId = 0;
ZEND_NAMED_FUNCTION( sleep_resuming_after_interruption )
{
    zend_long seconds;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_LONG( seconds )
    ZEND_PARSE_PARAMETERS_END();

    sleepResumingAfterInterruption(
        seconds
        , /* nanoSeconds */ 0
        , g_functionsToInterceptData[ sleep_resuming_after_interruption_interceptRegistrationId ].originalHandler
        , sleep_parseRetVal
        , sleep_setSuccessRetVal
        , INTERNAL_FUNCTION_PARAM_PASSTHRU );
}

static uint32_t usleep_resuming_after_interruption_interceptRegistrationId = 0;
ZEND_NAMED_FUNCTION( usleep_resuming_after_interruption )
{
    zend_long microSeconds;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_LONG( microSeconds )
    ZEND_PARSE_PARAMETERS_END();

    sleepResumingAfterInterruption(
        /* seconds */ 0
        , /* nanoSeconds */ microSeconds * 1000
        , g_functionsToInterceptData[ usleep_resuming_after_interruption_interceptRegistrationId ].originalHandler
        , usleep_parseRetVal
        , usleep_setSuccessRetVal
        , INTERNAL_FUNCTION_PARAM_PASSTHRU );
}

static uint32_t time_nanosleep_resuming_after_interruption_interceptRegistrationId = 0;
ZEND_NAMED_FUNCTION( time_nanosleep_resuming_after_interruption )
{
    zend_long seconds;
    zend_long nanoSeconds;

    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_LONG( seconds )
        Z_PARAM_LONG( nanoSeconds )
    ZEND_PARSE_PARAMETERS_END();

    sleepResumingAfterInterruption(
        seconds
        , nanoSeconds
        , g_functionsToInterceptData[ time_nanosleep_resuming_after_interruption_interceptRegistrationId ].originalHandler
        , time_nanosleep_parseRetVal
        , time_nanosleep_setSuccessRetVal
        , INTERNAL_FUNCTION_PARAM_PASSTHRU );
}
#if HAVE_NANOSLEEP
#endif // #if HAVE_NANOSLEEP

ResultCode replaceSleepWithResumingAfterSignalImpl()
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    ResultCode resultCode;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmInterceptCallsToInternalFunctionEx( "sleep", &sleep_resuming_after_interruption_interceptRegistrationId, sleep_resuming_after_interruption ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmInterceptCallsToInternalFunctionEx( "usleep", &usleep_resuming_after_interruption_interceptRegistrationId, usleep_resuming_after_interruption ) );
    #if HAVE_NANOSLEEP
    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmInterceptCallsToInternalFunctionEx( "time_nanosleep", &time_nanosleep_resuming_after_interruption_interceptRegistrationId, time_nanosleep_resuming_after_interruption ) );
    #endif // #if HAVE_NANOSLEEP

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    return resultCode;

    failure:
    goto finally;
}
