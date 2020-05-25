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
#include "util_for_PHP.h"
#include "elasticapm_alloc.h"
#include "numbered_intercepting_callbacks.h"
#include "tracer_PHP_part.h"

#define ELASTICAPM_CURRENT_LOG_CATEGORY ELASTICAPM_CURRENT_LOG_CATEGORY_EXT_API

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
    maxFunctionsToIntercept = numberedInterceptingCallbacksCount
};
static uint32_t g_nextFreeFunctionToInterceptId = 0;
struct CallToInterceptData
{
    zif_handler originalHandler;
    zend_function* funcEntry;
};
typedef struct CallToInterceptData CallToInterceptData;
static CallToInterceptData g_functionsToInterceptData[maxFunctionsToIntercept];

static
void internalFunctionCallInterceptingImpl( uint32_t funcToInterceptId, zend_execute_data* execute_data, zval* return_value )
{
    ELASTICAPM_LOG_TRACE_FUNCTION_ENTRY_MSG( "funcToInterceptId: %u", funcToInterceptId );

    ResultCode resultCode;

    zval preHookRetVal;
    ZVAL_UNDEF( &preHookRetVal );

    ELASTICAPM_CALL_IF_FAILED_GOTO( tracerPhpPartInterceptedCallPreHook( funcToInterceptId, execute_data, &preHookRetVal ) );

    g_functionsToInterceptData[ funcToInterceptId ].originalHandler( execute_data, return_value );

    ELASTICAPM_CALL_IF_FAILED_GOTO( tracerPhpPartInterceptedCallPostHook( funcToInterceptId, preHookRetVal, *return_value ) );

    resultCode = resultSuccess;

    finally:
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
    ELASTICAPM_FOR_EACH_BACKWARDS( i, g_nextFreeFunctionToInterceptId )
    {
        CallToInterceptData* data = &( g_functionsToInterceptData[ i ] );

        data->funcEntry->internal_function.handler = data->originalHandler;
    }

    g_nextFreeFunctionToInterceptId = 0;
}

bool addToFunctionsToInterceptData( zend_function* funcEntry, uint32_t* funcToInterceptId )
{
    if ( g_nextFreeFunctionToInterceptId >= maxFunctionsToIntercept )
    {
        ELASTICAPM_LOG_ERROR( "Reached maxFunctionsToIntercept."
                              " maxFunctionsToIntercept: %u. g_nextFreeFunctionToInterceptId: %u."
                              , maxFunctionsToIntercept, g_nextFreeFunctionToInterceptId );
        return false;
    }

    *funcToInterceptId = g_nextFreeFunctionToInterceptId ++;
    g_functionsToInterceptData[ *funcToInterceptId ].funcEntry = funcEntry;
    g_functionsToInterceptData[ *funcToInterceptId ].originalHandler = funcEntry->internal_function.handler;
    funcEntry->internal_function.handler = g_numberedInterceptingCallback[ *funcToInterceptId ];

    return true;
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

    if ( ! addToFunctionsToInterceptData( funcEntry, funcToInterceptId ) )
    {
        resultCode = resultFailure;
        goto failure;
    }

    resultCode = resultSuccess;

    finally:

    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    return resultCode;

    failure:
    goto finally;
}

ResultCode elasticApmInterceptCallsToInternalFunction( String functionName, uint32_t* funcToInterceptId )
{
    ELASTICAPM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "functionName: `%s'", functionName );

    ResultCode resultCode;

    zend_function* funcEntry = zend_hash_str_find_ptr( EG( function_table ), functionName, strlen( functionName ) );
    if ( funcEntry == NULL )
    {
        ELASTICAPM_LOG_ERROR( "zend_hash_str_find_ptr( EG( function_table ), ... ) failed."
                              " functionName: `%s'", functionName );
        resultCode = resultFailure;
        goto failure;
    }

    if ( ! addToFunctionsToInterceptData( funcEntry, funcToInterceptId ) )
    {
        resultCode = resultFailure;
        goto failure;
    }

    resultCode = resultSuccess;

    finally:

    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    return resultCode;

    failure:
    goto finally;
}

ResultCode elasticApmSendToServer( StringView serializedMetadata, StringView serializedEvents )
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

    ELASTICAPM_CALL_IF_FAILED_GOTO( saveMetadataFromPhpPart( &tracer->requestScoped, serializedMetadata ) );
    sendEventsToApmServer( config, serializedEvents );

    resultCode = resultSuccess;

    finally:
    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    return resultCode;

    failure:
    goto finally;
}
