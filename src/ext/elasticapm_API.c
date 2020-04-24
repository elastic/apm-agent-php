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

String elasticApmGetCurrentTransactionId()
{
    const Transaction* const currentTransaction = getGlobalTracer()->currentTransaction;
    const String result = currentTransaction == NULL ? NULL : currentTransaction->id;

    ELASTICAPM_LOG_TRACE( "Result: %s", result == NULL ? "NULL" : result );
    return result;
}

String elasticApmGetCurrentTraceId()
{
    const Transaction* const currentTransaction = getGlobalTracer()->currentTransaction;
    const String result = currentTransaction == NULL ? NULL : currentTransaction->traceId;

    ELASTICAPM_LOG_TRACE( "Result: %s", result == NULL ? "NULL" : result );
    return result;
}

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

zif_handler g_interceptedFuncOriginalHandler = NULL;
String g_interceptedFuncPreHookFunc = NULL;
String g_interceptedFuncPostHookFunc = NULL;

ZEND_NAMED_FUNCTION( interceptedFuncReplacement )
{
    if ( g_interceptedFuncPreHookFunc != NULL )
    {
        enum{ maxArgsCount = 100 };
        zval args[ maxArgsCount ];
        uint32_t argsCount;
        getArgsFromZendExecuteData( execute_data, maxArgsCount, args, &argsCount );
        callPhpFunctionEx( stringToStringView( g_interceptedFuncPreHookFunc ), logLevel_debug, argsCount, args );
    }

    // if we want to call the original function
    g_interceptedFuncOriginalHandler( INTERNAL_FUNCTION_PARAM_PASSTHRU );

    if ( g_interceptedFuncPostHookFunc != NULL )
    {
        callPhpFunctionEx( stringToStringView( g_interceptedFuncPostHookFunc ), logLevel_debug, 1, return_value );
    }
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

ResultCode elasticApmGetInterceptCallsToPhpFunction(
        String funcToIntercept
        , String preHookFunc
        , String postHookFunc
)
{
    ELASTICAPM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "funcToIntercept: `%s'; preHookFunc: `%s'; postHookFunc: `%s'"
                                             , funcToIntercept, preHookFunc, postHookFunc );
    ResultCode resultCode;

//    ELASTICAPM_LOG_NOTICE( "Content of EG( function_table ):" );
//    zend_hash_apply_with_arguments( EG( function_table ), print_key_apply_cb, 0 );
//
//    ELASTICAPM_LOG_NOTICE( "Content of EG( class_table ):" );
//    zend_hash_apply_with_arguments( EG( class_table ), print_key_apply_cb, 0 );
//
//    ELASTICAPM_LOG_NOTICE( "Content of CG( class_table ):" );
//    zend_hash_apply_with_arguments( CG( class_table ), print_key_apply_cb, 0 );

//    ELASTICAPM_LOG_NOTICE( "Content of CG( class_table ) + sub-tables:" );
//    zend_hash_apply_with_arguments( CG( class_table ), class_table_apply_cb, 0 );

    zend_function* originalFuncEntry = zend_hash_str_find_ptr( EG( function_table ), funcToIntercept, strlen( funcToIntercept ) );
    if ( originalFuncEntry == NULL )
    {
        ELASTICAPM_LOG_ERROR( "zend_hash_str_find_ptr failed. funcToIntercept: `%s'", funcToIntercept );
        resultCode = resultFailure;
        goto failure;
    }

    g_interceptedFuncPreHookFunc = strdup( preHookFunc );
    g_interceptedFuncPostHookFunc = strdup( postHookFunc );
    g_interceptedFuncOriginalHandler = originalFuncEntry->internal_function.handler;
    originalFuncEntry->internal_function.handler = interceptedFuncReplacement;

    resultCode = resultSuccess;

    finally:

    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    return resultCode;

    failure:
    goto finally;
}

ResultCode elasticApmGetInterceptCallsToPhpMethod( String className
                                                   , String methodName
                                                   , String preHookFunc
                                                   , String postHookFunc
)
{
    ELASTICAPM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "className: `%s'; methodName: `%s'; preHookFunc: `%s'; postHookFunc: `%s'"
                                             , className, methodName, preHookFunc, postHookFunc );
    ResultCode resultCode;

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

    zend_function* originalFuncEntry = zend_hash_str_find_ptr( &classEntry->function_table, methodName, strlen( methodName ) );
    if ( originalFuncEntry == NULL )
    {
        ELASTICAPM_LOG_ERROR( "zend_hash_str_find_ptr( &classEntry->function_table, ... ) failed."
                              " className: `%s'; methodName: `%s'", className, methodName );
        resultCode = resultFailure;
        goto failure;
    }

    g_interceptedFuncPreHookFunc = strdup( preHookFunc );
    g_interceptedFuncPostHookFunc = strdup( postHookFunc );
    g_interceptedFuncOriginalHandler = originalFuncEntry->internal_function.handler;
    originalFuncEntry->internal_function.handler = interceptedFuncReplacement;

    resultCode = resultSuccess;

    finally:

    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    return resultCode;

    failure:
    goto finally;
}
