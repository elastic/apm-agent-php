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
        callPhpFunction( stringToStringView( g_interceptedFuncPreHookFunc ), logLevel_debug );
    }

    // if we want to call the original function
    g_interceptedFuncOriginalHandler( INTERNAL_FUNCTION_PARAM_PASSTHRU );

    if ( g_interceptedFuncPostHookFunc != NULL )
    {
        callPhpFunction( stringToStringView( g_interceptedFuncPostHookFunc ), logLevel_debug );
    }
}

//int EG_function_table_apply_cb(zval *pDest, int num_args, va_list args, zend_hash_key *hash_key)
//{
//    ELASTICAPM_LOG_NOTICE( "hash_key: %s", ZSTR_VAL( hash_key->key ) );
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

//    ELASTICAPM_LOG_NOTICE( "Content of EG( function_table ):\n" );
//    zend_hash_apply_with_arguments( EG( function_table ), EG_function_table_apply_cb, 0 );

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
