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
#include "util.h"
#include "log.h"
#include "Tracer.h"
#include "elastic_apm_alloc.h"
#include "numbered_intercepting_callbacks.h"
#include "tracer_PHP_part.h"
#include "backend_comm.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_EXT_API

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

    ELASTIC_APM_LOG_TRACE_FUNCTION_EXIT_RESULT_CODE_MSG( "Option's: name: `%s', value: %s", optionName, getOptValueByNameRes.streamedParsedValue );
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
//struct CallToInterceptData
//{
//    zif_handler originalHandler;
//    zend_function* funcEntry;
//};
//typedef struct CallToInterceptData CallToInterceptData;
static CallToInterceptData g_functionsToInterceptData[maxFunctionsToIntercept];

static uint32_t g_interceptedCallInProgressRegistrationId = 0;

ClassAndFunctionNames g_callsToBootstrapPhpPart[ g_callsToBootstrapPhpPartCount ] =
{
    { .className = NULL, .functionName = "curl_init" }
    /* className must be in lower case */
    , { .className = "pdo", .functionName = "__construct" }
};

bool g_callsToBootstrapPhpPart_interceptRegistrationIds_is_set[ g_callsToBootstrapPhpPartCount ];
uint32_t g_callsToBootstrapPhpPart_interceptRegistrationIds[ g_callsToBootstrapPhpPartCount ];

void initForwardInterceptedCallToAutoInstrumentation()
{
    ELASTIC_APM_FOR_EACH_INDEX( i, g_callsToBootstrapPhpPartCount )
    {
        g_callsToBootstrapPhpPart_interceptRegistrationIds_is_set[ i ] = false;
    }
}

static inline
bool areNamesEqual( String name1, String name2 )
{
    return strcmp( name1, name2 ) == 0;
}

static inline
bool areOptionalNamesEqual( String name1, String name2 )
{
    if ( ( name1 == NULL ) || ( name2 == NULL ) )
    {
        return ( name1 == NULL ) == ( name2 == NULL );
    }
    return areNamesEqual( name1, name2 );
}

void setInterceptRegistrationIdForForward( String className, String functionName, uint32_t interceptRegistrationId )
{
    ELASTIC_APM_FOR_EACH_INDEX( i, g_callsToBootstrapPhpPartCount )
    {
        if ( ! ( areOptionalNamesEqual( className, g_callsToBootstrapPhpPart[ i ].className ) && areNamesEqual( functionName, g_callsToBootstrapPhpPart[ i ].functionName ) ) )
        {
            continue;
        }

        g_callsToBootstrapPhpPart_interceptRegistrationIds_is_set[ i ] = true;
        g_callsToBootstrapPhpPart_interceptRegistrationIds[ i ] = interceptRegistrationId;
        return;
    }
}

bool forwardInterceptedCallToAutoInstrumentation( String className, String functionName, zend_execute_data* execute_data, zval* return_value )
{
    ELASTIC_APM_FOR_EACH_INDEX( i, g_callsToBootstrapPhpPartCount )
    {
        if ( ! g_callsToBootstrapPhpPart_interceptRegistrationIds_is_set[ i ] )
        {
            continue;
        }

        if ( ! ( areOptionalNamesEqual( className, g_callsToBootstrapPhpPart[ i ].className ) && areNamesEqual( functionName, g_callsToBootstrapPhpPart[ i ].functionName ) ) )
        {
            continue;
        }

        internalFunctionCallInterceptingImpl( g_callsToBootstrapPhpPart_interceptRegistrationIds[ i ], execute_data, return_value );
        return true;
    }

    return false;
}

static
void internalFunctionCallInterceptingImpl( uint32_t interceptRegistrationId, zend_execute_data* execute_data, zval* return_value )
{
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

bool addToFunctionsToInterceptData( String className, String functionName, zend_function* funcEntry, uint32_t* interceptRegistrationId )
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
    funcEntry->internal_function.handler = g_numberedInterceptingCallback[ *interceptRegistrationId ];
    setInterceptRegistrationIdForForward( className, functionName, *interceptRegistrationId );

    return true;
}


ResultCode elasticApmInterceptCallsToInternalMethod( String className, String methodName, uint32_t* interceptRegistrationId )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "className: `%s'; methodName: `%s'", className, methodName );

    ResultCode resultCode;

    zend_class_entry* classEntry = zend_hash_str_find_ptr( CG( class_table ), className, strlen( className ) );
    if ( classEntry == NULL )
    {
        ELASTIC_APM_LOG_ERROR( "zend_hash_str_find_ptr( CG( class_table ), ... ) failed. className: `%s'", className );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    zend_function* funcEntry = zend_hash_str_find_ptr( &classEntry->function_table, methodName, strlen( methodName ) );
    if ( funcEntry == NULL )
    {
        ELASTIC_APM_LOG_ERROR( "zend_hash_str_find_ptr( &classEntry->function_table, ... ) failed."
                               " className: `%s'; methodName: `%s'", className, methodName );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if ( ! addToFunctionsToInterceptData( className, methodName, funcEntry, interceptRegistrationId ) )
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

ResultCode elasticApmInterceptCallsToInternalFunction( String functionName, uint32_t* interceptRegistrationId )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "functionName: `%s'", functionName );

    ResultCode resultCode;

    zend_function* funcEntry = zend_hash_str_find_ptr( EG( function_table ), functionName, strlen( functionName ) );
    if ( funcEntry == NULL )
    {
        ELASTIC_APM_LOG_ERROR( "zend_hash_str_find_ptr( EG( function_table ), ... ) failed."
                               " functionName: `%s'", functionName );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if ( ! addToFunctionsToInterceptData( /* className */ NULL, functionName, funcEntry, interceptRegistrationId ) )
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

static inline bool longToBool( long longVal )
{
    return longVal != 0;
}

ResultCode elasticApmSendToServer(
        long disableSend
        , double serverTimeoutMilliseconds
        , StringView userAgentHttpHeader
        , StringView serializedEvents )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    ResultCode resultCode;
    Tracer* const tracer = getGlobalTracer();

    ELASTIC_APM_CALL_IF_FAILED_GOTO(
            sendEventsToApmServer( longToBool( disableSend )
                                   , serverTimeoutMilliseconds
                                   , getTracerCurrentConfigSnapshot( tracer )
                                   , userAgentHttpHeader
                                   , serializedEvents ) );

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    goto finally;
}
