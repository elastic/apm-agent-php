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

#include "elasticapm_API.h"
#include "util.h"
#include "log.h"
#include "Tracer.h"
#include "util_for_php.h"
#include "elasticapm_alloc.h"
#include "numbered_intercepting_callbacks.h"

bool elasticApmIsEnabled()
{
    const bool result = getTracerCurrentConfigSnapshot( getGlobalTracer() )->enabled;

    ELASTICAPM_LOG_TRACE( "Result: %s", boolToString( result ) );
    return result;
}

ResultCode elasticApmGetConfigOption( String optionName, zval* return_value )
{
    ELASTICAPM_LOG_TRACE_FUNCTION_ENTRY_MSG( "optionName: `%s'", optionName );

    char txtOutStreamBuf[ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    GetConfigManagerOptionValueByNameResult getOptValueByNameRes;
    getOptValueByNameRes.txtOutStream = ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    getOptValueByNameRes.streamedParsedValue = NULL;
    const ResultCode resultCode = getConfigManagerOptionValueByName(
            getGlobalTracer()->configManager
            , optionName
            , &getOptValueByNameRes );
    if ( resultCode == resultSuccess ) *return_value = getOptValueByNameRes.parsedValueAsZval;

    ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG(
            "ResultCode: %s. Option's: name: `%s', value: %s"
            , resultCodeToString( resultCode ), optionName, getOptValueByNameRes.streamedParsedValue );
    return resultCode;
}

// TODO: Sergey Kleyman: Move to Tracer
enum
{
    maxCallsToIntercept = numberedInterceptingCallbacksCount
};
static uint32_t g_nextFreeCallToInterceptId = 0;
struct CallToInterceptData
{
    zif_handler originalHandler;
    zend_function* funcEntry;
};
typedef struct CallToInterceptData CallToInterceptData;
static CallToInterceptData g_callsToInterceptData[maxCallsToIntercept];

#define ELASTICAPM_PHP_INTERCEPTED_CALL_HOOK_FUNC_PREFIX "\\Elastic\\Apm\\Impl\\AutoInstrument\\InterceptionManager::"
#define ELASTICAPM_PHP_INTERCEPTED_CALL_PRE_HOOK ELASTICAPM_PHP_INTERCEPTED_CALL_HOOK_FUNC_PREFIX "interceptedCallPreHook"
#define ELASTICAPM_PHP_INTERCEPTED_CALL_POST_HOOK ELASTICAPM_PHP_INTERCEPTED_CALL_HOOK_FUNC_PREFIX "interceptedCallPostHook"

static
void internalFunctionCallInterceptingImpl( uint32_t funcToInterceptId, zend_execute_data* execute_data, zval* return_value )
{
    ResultCode resultCode;

    zval funcToInterceptIdAsZval;
    ZVAL_UNDEF( &funcToInterceptIdAsZval );
    zval preHookRetVal;
    ZVAL_UNDEF( &preHookRetVal );

    enum { maxInterceptedCallArgsCount = 100 };
    zval preHookArgs[maxInterceptedCallArgsCount];

    // The first argument to InterceptionManager::interceptedCallPreHook is $funcToInterceptId
    ZVAL_LONG( &funcToInterceptIdAsZval, funcToInterceptId )
    preHookArgs[ 0 ] = funcToInterceptIdAsZval;

    uint32_t interceptedCallArgsCount;
    getArgsFromZendExecuteData( execute_data, maxInterceptedCallArgsCount - 1, &( preHookArgs[ 1 ] ), &interceptedCallArgsCount );
    ELASTICAPM_CALL_IF_FAILED_GOTO(
            callPhpFunctionRetZval(
                    ELASTICAPM_STRING_LITERAL_TO_VIEW( ELASTICAPM_PHP_INTERCEPTED_CALL_PRE_HOOK )
                    , logLevel_debug
                    , interceptedCallArgsCount + 1
                    , preHookArgs
                    , &preHookRetVal ) );

    g_callsToInterceptData[ funcToInterceptId ].originalHandler( INTERNAL_FUNCTION_PARAM_PASSTHRU );

    zval postHookArgs[] = { preHookRetVal, *return_value };
    ELASTICAPM_CALL_IF_FAILED_GOTO(
            callPhpFunctionRetVoid(
                    ELASTICAPM_STRING_LITERAL_TO_VIEW( ELASTICAPM_PHP_INTERCEPTED_CALL_POST_HOOK )
                    , logLevel_debug
                    , ELASTICAPM_STATIC_ARRAY_SIZE( postHookArgs )
                    , postHookArgs ) );

    resultCode = resultSuccess;

    finally:
    zval_dtor( &funcToInterceptIdAsZval );
    zval_dtor( &preHookRetVal );

    ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    ELASTICAPM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}

//static
//int print_key_apply_cb( zval* pDest, int num_args, va_list args, zend_hash_key* hash_key )
//{
//    char buffer[ 100 ];
//    zend_spprintf( buffer, ELASTICAPM_STATIC_ARRAY_SIZE( buffer ) - 1, "%Z", pDest );
//
//    ELASTICAPM_LOG_NOTICE( "    %s | %Z", ZSTR_VAL( hash_key->key ), pDest );
//    return ZEND_HASH_APPLY_KEEP;
//}
//
//static
//int class_table_apply_cb( zval* pDest, int num_args, va_list args, zend_hash_key* hash_key )
//{
//    ELASTICAPM_LOG_NOTICE( "%s", ZSTR_VAL( hash_key->key ) );
//
//    zend_class_entry* classEntry = (zend_class_entry*)pDest;
//    zend_hash_apply_with_arguments( &classEntry->function_table, print_key_apply_cb, 0 );
//
//    return ZEND_HASH_APPLY_KEEP;
//}

//ResultCode elasticApmInterceptCallsToFunction(
//        String funcToIntercept
//        , String funcToCallBeforeIntercepted
//        , String funcToCallAfterIntercepted
//)
//{
//    ELASTICAPM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "funcToIntercept: `%s'; preHookFunc: `%s'; postHookFunc: `%s'"
//                                             , funcToIntercept, funcToCallBeforeIntercepted, funcToCallAfterIntercepted );
//    ResultCode resultCode;
//
////    ELASTICAPM_LOG_NOTICE( "Content of EG( function_table ):" );
////    zend_hash_apply_with_arguments( EG( function_table ), print_key_apply_cb, 0 );
////
////    ELASTICAPM_LOG_NOTICE( "Content of EG( class_table ):" );
////    zend_hash_apply_with_arguments( EG( class_table ), print_key_apply_cb, 0 );
////
////    ELASTICAPM_LOG_NOTICE( "Content of CG( class_table ):" );
////    zend_hash_apply_with_arguments( CG( class_table ), print_key_apply_cb, 0 );
//
////    ELASTICAPM_LOG_NOTICE( "Content of CG( class_table ) + sub-tables:" );
////    zend_hash_apply_with_arguments( CG( class_table ), class_table_apply_cb, 0 );
//
//    zend_function* originalFuncEntry = zend_hash_str_find_ptr( EG( function_table ), funcToIntercept, strlen( funcToIntercept ) );
//    if ( originalFuncEntry == NULL )
//    {
//        ELASTICAPM_LOG_ERROR( "zend_hash_str_find_ptr failed. funcToIntercept: `%s'", funcToIntercept );
//        resultCode = resultFailure;
//        goto failure;
//    }
//
//    g_interceptedFuncPreHookFunc = strdup( funcToCallBeforeIntercepted );
//    g_interceptedFuncPostHookFunc = strdup( funcToCallAfterIntercepted );
//    g_interceptedFuncOriginalHandler = originalFuncEntry->internal_function.handler;
//    originalFuncEntry->internal_function.handler = interceptedCallReplacement;
//
//    resultCode = resultSuccess;
//
//    finally:
//
//    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
//    return resultCode;
//
//    failure:
//    goto finally;
//}

void resetCallInterceptionOnRequestShutdown()
{
    // We restore original handlers in the reverse order
    // so that if the same function is registered for interception more than once
    // the original handler will be restored correctly
    ELASTICAPM_FOR_EACH_BACKWARDS( i, g_nextFreeCallToInterceptId )
    {
        CallToInterceptData* data = &( g_callsToInterceptData[ i ] );

        data->funcEntry->internal_function.handler = data->originalHandler;
    }

    g_nextFreeCallToInterceptId = 0;
}

ResultCode elasticApmInterceptCallsToInternalMethod( String className, String methodName, uint32_t* funcToInterceptId )
{
    ELASTICAPM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "className: `%s'; methodName: `%s'", className, methodName );

    ResultCode resultCode;

    // TODO: Sergey Kleyman: Implement: Actually convert className to lower case
    #ifdef PHP_WIN32
    className = "pdo";
    #endif

    zend_class_entry* classEntry = zend_hash_str_find_ptr( CG( class_table ), className, strlen( className ) );
    if ( classEntry == NULL )
    {
        ELASTICAPM_LOG_ERROR( "zend_hash_str_find_ptr( CG( class_table ), ... ) failed. className: `%s'", className );
        resultCode = resultFailure;
        goto failure;
    }

    zend_function* funcEntry = zend_hash_str_find_ptr( &classEntry->function_table, methodName, strlen( methodName ) );
    if ( funcEntry == NULL )
    {
        ELASTICAPM_LOG_ERROR( "zend_hash_str_find_ptr( &classEntry->function_table, ... ) failed."
                              " className: `%s'; methodName: `%s'", className, methodName );
        resultCode = resultFailure;
        goto failure;
    }


    if ( g_nextFreeCallToInterceptId >= maxCallsToIntercept )
    {
        ELASTICAPM_LOG_ERROR( "Reached maxCallsToIntercept."
                              " maxCallsToIntercept: %u. g_nextFreeCallToInterceptId: %u."
                              , maxCallsToIntercept, g_nextFreeCallToInterceptId );
        resultCode = resultFailure;
        goto failure;
    }

    *funcToInterceptId = g_nextFreeCallToInterceptId ++;
    g_callsToInterceptData[ *funcToInterceptId ].funcEntry = funcEntry;
    g_callsToInterceptData[ *funcToInterceptId ].originalHandler = funcEntry->internal_function.handler;
    funcEntry->internal_function.handler = g_numberedInterceptingCallback[ *funcToInterceptId ];

    resultCode = resultSuccess;

    finally:

    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    return resultCode;

    failure:
    goto finally;
}

ResultCode elasticApmSendToServer( String serializedEvents )
{
    ELASTICAPM_LOG_DEBUG_FUNCTION_ENTRY();

    ResultCode resultCode;
    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* const config = getTracerCurrentConfigSnapshot( tracer );

    if ( ! tracer->isInited )
    {
        resultCode = resultFailure;
        ELASTICAPM_LOG_ERROR( "Extension is not initialized" );
        goto failure;
    }

    sendEventsToApmServer( config, serializedEvents );

    resultCode = resultSuccess;

    finally:
    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    return resultCode;

    failure:
    goto finally;
}
