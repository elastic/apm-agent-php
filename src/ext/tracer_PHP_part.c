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

#include "tracer_PHP_part.h"
#include "log.h"
#include "Tracer.h"
#include "util_for_PHP.h"
#include "basic_macros.h"

#define ELASTICAPM_CURRENT_LOG_CATEGORY ELASTICAPM_CURRENT_LOG_CATEGORY_C_TO_PHP

#define ELASTICAPM_PHP_PART_FUNC_PREFIX "\\Elastic\\Apm\\Impl\\AutoInstrument\\PhpPartFacade::"
#define ELASTICAPM_PHP_PART_BOOTSTRAP_FUNC ELASTICAPM_PHP_PART_FUNC_PREFIX "bootstrap"
#define ELASTICAPM_PHP_PART_SHUTDOWN_FUNC ELASTICAPM_PHP_PART_FUNC_PREFIX "shutdown"
#define ELASTICAPM_PHP_PART_INTERCEPTED_CALL_FUNC ELASTICAPM_PHP_PART_FUNC_PREFIX "interceptedCall"

ResultCode bootstrapTracerPhpPart( const ConfigSnapshot* config, const TimePoint* requestInitStartTime )
{
    char txtOutStreamBuf[ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    ELASTICAPM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "config->bootstrapPhpPartFile: %s"
                                             , streamUserString( config->bootstrapPhpPartFile, &txtOutStream ) );

    ResultCode resultCode;
    bool bootstrapTracerPhpPartRetVal;
    zval maxEnabledLevel;
    ZVAL_UNDEF( &maxEnabledLevel );
    zval requestInitStartTimeZval;
    ZVAL_UNDEF( &requestInitStartTimeZval );

    if ( config->bootstrapPhpPartFile == NULL )
    {
        // For now we don't consider `bootstrap_php_part_file' option not being set as a failure
        GetConfigManagerOptionMetadataResult getMetaRes;
        getConfigManagerOptionMetadata( getGlobalTracer()->configManager, optionId_bootstrapPhpPartFile, &getMetaRes );
        ELASTICAPM_LOG_INFO( "Configuration option `%s' is not set", getMetaRes.optName );
        resultCode = resultSuccess;
        goto finally;
    }

    ELASTICAPM_CALL_IF_FAILED_GOTO( loadPhpFile( config->bootstrapPhpPartFile ) );

    ZVAL_LONG( &maxEnabledLevel, getGlobalTracer()->logger.maxEnabledLevel )
    ZVAL_DOUBLE( &requestInitStartTimeZval, ((double)timePointToEpochMicroseconds( requestInitStartTime )) )
    zval bootstrapTracerPhpPartArgs[] = { maxEnabledLevel, requestInitStartTimeZval };
    ELASTICAPM_CALL_IF_FAILED_GOTO( callPhpFunctionRetBool(
            ELASTICAPM_STRING_LITERAL_TO_VIEW( ELASTICAPM_PHP_PART_BOOTSTRAP_FUNC )
            , logLevel_debug
            , /* argsCount */ ELASTICAPM_STATIC_ARRAY_SIZE( bootstrapTracerPhpPartArgs )
            , /* args */ bootstrapTracerPhpPartArgs
            , &bootstrapTracerPhpPartRetVal ) );
    if ( ! bootstrapTracerPhpPartRetVal )
    {
        ELASTICAPM_LOG_CRITICAL( "%s failed (returned false). See log for more details.", ELASTICAPM_PHP_PART_BOOTSTRAP_FUNC );
        resultCode = resultFailure;
        goto failure;
    }

    resultCode = resultSuccess;

    finally:
    zval_dtor( &requestInitStartTimeZval );
    zval_dtor( &maxEnabledLevel );
    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    return resultCode;

    failure:
    goto finally;
}

void shutdownTracerPhpPart( const ConfigSnapshot* config )
{
    ELASTICAPM_LOG_DEBUG_FUNCTION_ENTRY();

    ResultCode resultCode;

    if ( config->bootstrapPhpPartFile == NULL )
    {
        // For now we don't consider `bootstrap_php_part_file' option not being set as a failure
        GetConfigManagerOptionMetadataResult getMetaRes;
        getConfigManagerOptionMetadata( getGlobalTracer()->configManager, optionId_bootstrapPhpPartFile, &getMetaRes );
        ELASTICAPM_LOG_INFO( "Configuration option `%s' is not set", getMetaRes.optName );
        resultCode = resultSuccess;
        goto finally;
    }

    ELASTICAPM_CALL_IF_FAILED_GOTO( callPhpFunctionRetVoid(
            ELASTICAPM_STRING_LITERAL_TO_VIEW( ELASTICAPM_PHP_PART_SHUTDOWN_FUNC )
            , logLevel_debug
            , /* argsCount */ 0
            , /* args */ NULL ) );

    resultCode = resultSuccess;

    finally:
    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    ELASTICAPM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}

void tracerPhpPartInterceptedCall( uint32_t funcToInterceptId, zend_execute_data* execute_data, zval* return_value )
{
    ELASTICAPM_LOG_TRACE_FUNCTION_ENTRY_MSG( "funcToInterceptId: %u", funcToInterceptId );

    ResultCode resultCode;

    zval funcToInterceptIdAsZval;
    ZVAL_UNDEF( &funcToInterceptIdAsZval );

    enum
    {
        maxInterceptedCallArgsCount = 100
    };
    zval phpPartArgs[maxInterceptedCallArgsCount + 2];

    // The first argument to PHP part's interceptedCall() is $funcToInterceptId
    ZVAL_LONG( &funcToInterceptIdAsZval, funcToInterceptId )
    phpPartArgs[ 0 ] = funcToInterceptIdAsZval;

    // The second argument to PHP part's interceptedCall() is $thisObj
    if (Z_TYPE(execute_data->This) == IS_UNDEF)
    {
        ZVAL_NULL( &phpPartArgs[ 1 ] );
    }
    else
    {
        phpPartArgs[ 1 ] = execute_data->This;
    }

    uint32_t interceptedCallArgsCount;
    getArgsFromZendExecuteData( execute_data, maxInterceptedCallArgsCount, &( phpPartArgs[ 2 ] ), &interceptedCallArgsCount );
    ELASTICAPM_CALL_IF_FAILED_GOTO(
            callPhpFunctionRetZval(
                    ELASTICAPM_STRING_LITERAL_TO_VIEW( ELASTICAPM_PHP_PART_INTERCEPTED_CALL_FUNC )
                    , logLevel_debug
                    , interceptedCallArgsCount + 2
                    , phpPartArgs
                    , /* out */ return_value ) );
    ELASTICAPM_LOG_TRACE( "Successfully finished call to PHP part. Return value type: %u", Z_TYPE_P( return_value ) );

    resultCode = resultSuccess;

    finally:
    zval_dtor( &funcToInterceptIdAsZval );

    ELASTICAPM_LOG_FUNCTION_EXIT_MSG_WITH_LEVEL( resultCode == resultSuccess ? logLevel_trace : logLevel_error
                                                 , "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    ELASTICAPM_UNUSED(resultCode);
    return;

    failure:
    goto finally;
}
