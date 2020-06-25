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

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_C_TO_PHP

#define ELASTIC_APM_PHP_PART_FUNC_PREFIX "\\Elastic\\Apm\\Impl\\AutoInstrument\\PhpPartFacade::"
#define ELASTIC_APM_PHP_PART_BOOTSTRAP_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "bootstrap"
#define ELASTIC_APM_PHP_PART_SHUTDOWN_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "shutdown"
#define ELASTIC_APM_PHP_PART_INTERCEPTED_CALL_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "interceptedCall"

ResultCode bootstrapTracerPhpPart( const ConfigSnapshot* config, const TimePoint* requestInitStartTime )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "config->bootstrapPhpPartFile: %s"
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
        ELASTIC_APM_LOG_INFO( "Configuration option `%s' is not set", getMetaRes.optName );
        resultCode = resultSuccess;
        goto finally;
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( loadPhpFile( config->bootstrapPhpPartFile ) );

    ZVAL_LONG( &maxEnabledLevel, getGlobalTracer()->logger.maxEnabledLevel )
    ZVAL_DOUBLE( &requestInitStartTimeZval, ((double)timePointToEpochMicroseconds( requestInitStartTime )) )
    zval bootstrapTracerPhpPartArgs[] = { maxEnabledLevel, requestInitStartTimeZval };
    ELASTIC_APM_CALL_IF_FAILED_GOTO( callPhpFunctionRetBool(
            ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_PHP_PART_BOOTSTRAP_FUNC )
            , logLevel_debug
            , /* argsCount */ ELASTIC_APM_STATIC_ARRAY_SIZE( bootstrapTracerPhpPartArgs )
            , /* args */ bootstrapTracerPhpPartArgs
            , &bootstrapTracerPhpPartRetVal ) );
    if ( ! bootstrapTracerPhpPartRetVal )
    {
        ELASTIC_APM_LOG_CRITICAL( "%s failed (returned false). See log for more details.", ELASTIC_APM_PHP_PART_BOOTSTRAP_FUNC );
        resultCode = resultFailure;
        goto failure;
    }

    resultCode = resultSuccess;

    finally:
    zval_dtor( &requestInitStartTimeZval );
    zval_dtor( &maxEnabledLevel );
    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    return resultCode;

    failure:
    goto finally;
}

void shutdownTracerPhpPart( const ConfigSnapshot* config )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    ResultCode resultCode;

    if ( config->bootstrapPhpPartFile == NULL )
    {
        // For now we don't consider `bootstrap_php_part_file' option not being set as a failure
        GetConfigManagerOptionMetadataResult getMetaRes;
        getConfigManagerOptionMetadata( getGlobalTracer()->configManager, optionId_bootstrapPhpPartFile, &getMetaRes );
        ELASTIC_APM_LOG_INFO( "Configuration option `%s' is not set", getMetaRes.optName );
        resultCode = resultSuccess;
        goto finally;
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( callPhpFunctionRetVoid(
            ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_PHP_PART_SHUTDOWN_FUNC )
            , logLevel_debug
            , /* argsCount */ 0
            , /* args */ NULL ) );

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    ELASTIC_APM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}

void tracerPhpPartInterceptedCall( uint32_t interceptRegistrationId, zend_execute_data* execute_data, zval* return_value )
{
    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY_MSG( "interceptRegistrationId: %u", interceptRegistrationId );

    ResultCode resultCode;

    zval interceptRegistrationIdAsZval;
    ZVAL_UNDEF( &interceptRegistrationIdAsZval );

    enum
    {
        maxInterceptedCallArgsCount = 100
    };
    zval phpPartArgs[maxInterceptedCallArgsCount + 2];

    // The first argument to PHP part's interceptedCall() is $interceptRegistrationId
    ZVAL_LONG( &interceptRegistrationIdAsZval, interceptRegistrationId )
    phpPartArgs[ 0 ] = interceptRegistrationIdAsZval;

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
    ELASTIC_APM_CALL_IF_FAILED_GOTO(
            callPhpFunctionRetZval(
                    ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_PHP_PART_INTERCEPTED_CALL_FUNC )
                    , logLevel_debug
                    , interceptedCallArgsCount + 2
                    , phpPartArgs
                    , /* out */ return_value ) );
    ELASTIC_APM_LOG_TRACE( "Successfully finished call to PHP part. Return value type: %u", Z_TYPE_P( return_value ) );

    resultCode = resultSuccess;

    finally:
    zval_dtor( &interceptRegistrationIdAsZval );

    ELASTIC_APM_LOG_FUNCTION_EXIT_MSG_WITH_LEVEL( resultCode == resultSuccess ? logLevel_trace : logLevel_error
                                                 , "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    ELASTIC_APM_UNUSED(resultCode);
    return;

    failure:
    goto finally;
}
