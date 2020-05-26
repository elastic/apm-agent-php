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

static zend_execute_data* g_interceptedCallZendExecuteData = NULL;
static zif_handler g_interceptedCallOriginalHandler = NULL;

static
void internalFunctionCallInterceptingImpl( uint32_t funcToInterceptId, zend_execute_data* execute_data, zval* return_value )
{
    ELASTICAPM_LOG_TRACE_FUNCTION_ENTRY_MSG( "funcToInterceptId: %u", funcToInterceptId );

    ELASTICAPM_ASSERT(g_interceptedCallZendExecuteData == NULL, "");
    ELASTICAPM_ASSERT(g_interceptedCallOriginalHandler == NULL, "");
    g_interceptedCallZendExecuteData = execute_data;
    g_interceptedCallOriginalHandler = g_functionsToInterceptData[ funcToInterceptId ].originalHandler;

    tracerPhpPartInterceptedCall( funcToInterceptId, execute_data, return_value );

    g_interceptedCallZendExecuteData = NULL;
    g_interceptedCallOriginalHandler = NULL;

    ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "funcToInterceptId: %u", funcToInterceptId );
}

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

void elasticApmCallInterceptedOriginal( zval* return_value )
{
    ELASTICAPM_LOG_TRACE_FUNCTION_ENTRY();

    ELASTICAPM_ASSERT(g_interceptedCallZendExecuteData != NULL, "");
    ELASTICAPM_ASSERT(g_interceptedCallOriginalHandler != NULL, "");

    g_interceptedCallOriginalHandler( g_interceptedCallZendExecuteData, return_value );

    ELASTICAPM_LOG_TRACE_FUNCTION_EXIT();
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
