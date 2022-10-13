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

#include "tracer_PHP_part.h"
#include "log.h"
#include "Tracer.h"
#include "util_for_PHP.h"
#include "basic_macros.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_C_TO_PHP

#define ELASTIC_APM_PHP_PART_FUNC_PREFIX "\\Elastic\\Apm\\Impl\\AutoInstrument\\PhpPartFacade::"
#define ELASTIC_APM_PHP_PART_BOOTSTRAP_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "bootstrap"
#define ELASTIC_APM_PHP_PART_SHUTDOWN_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "shutdown"
#define ELASTIC_APM_PHP_PART_INTERCEPTED_CALL_PRE_HOOK_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "interceptedCallPreHook"
#define ELASTIC_APM_PHP_PART_INTERCEPTED_CALL_POST_HOOK_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "interceptedCallPostHook"
#define ELASTIC_APM_PHP_PART_ON_PHP_ERROR_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "onPhpError"
#define ELASTIC_APM_PHP_PART_SET_LAST_THROWN_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "setLastThrown"
#define ELASTIC_APM_PHP_PART_EMPTY_METHOD_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "emptyMethod"

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
        // For now, we don't consider `bootstrap_php_part_file' option not being set as a failure
        GetConfigManagerOptionMetadataResult getMetaRes;
        getConfigManagerOptionMetadata( getGlobalTracer()->configManager, optionId_bootstrapPhpPartFile, &getMetaRes );
        ELASTIC_APM_LOG_ERROR( "Configuration option `%s' is not set", getMetaRes.optName );
        resultCode = resultSuccess;
        goto finally;
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( loadPhpFile( config->bootstrapPhpPartFile ) );

    ZVAL_LONG( &maxEnabledLevel, getGlobalTracer()->logger.maxEnabledLevel );
    ZVAL_DOUBLE( &requestInitStartTimeZval, ( (double) timePointToEpochMicroseconds( requestInitStartTime ) ) );
    zval bootstrapTracerPhpPartArgs[] = { maxEnabledLevel, requestInitStartTimeZval };
    ELASTIC_APM_CALL_IF_FAILED_GOTO( callPhpFunctionRetBool(
            ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_PHP_PART_BOOTSTRAP_FUNC )
            , /* argsCount */ ELASTIC_APM_STATIC_ARRAY_SIZE( bootstrapTracerPhpPartArgs )
            , /* args */ bootstrapTracerPhpPartArgs
            , &bootstrapTracerPhpPartRetVal ) );
    if ( ! bootstrapTracerPhpPartRetVal )
    {
        ELASTIC_APM_LOG_CRITICAL( "%s failed (returned false). See log for more details.", ELASTIC_APM_PHP_PART_BOOTSTRAP_FUNC );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    resultCode = resultSuccess;

    finally:
    zval_dtor( &requestInitStartTimeZval );
    zval_dtor( &maxEnabledLevel );
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
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
        // For now, we don't consider `bootstrap_php_part_file' option not being set as a failure
        GetConfigManagerOptionMetadataResult getMetaRes;
        getConfigManagerOptionMetadata( getGlobalTracer()->configManager, optionId_bootstrapPhpPartFile, &getMetaRes );
        ELASTIC_APM_LOG_ERROR( "Configuration option `%s' is not set", getMetaRes.optName );
        resultCode = resultSuccess;
        goto finally;
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( callPhpFunctionRetVoid(
            ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_PHP_PART_SHUTDOWN_FUNC )
            , /* argsCount */ 0
            , /* args */ NULL ) );

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    ELASTIC_APM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}

bool tracerPhpPartInterceptedCallPreHook( uint32_t interceptRegistrationId, zend_execute_data* execute_data )
{
    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY_MSG( "interceptRegistrationId: %u", interceptRegistrationId );

    ResultCode resultCode;
    zval preHookRetVal;
    bool shouldCallPostHook;

    zval interceptRegistrationIdAsZval;
    ZVAL_UNDEF( &interceptRegistrationIdAsZval );

    enum
    {
        maxInterceptedCallArgsCount = 100
    };
    zval phpPartArgs[maxInterceptedCallArgsCount + 2];

    // The first argument to PHP part's interceptedCallPreHook() is $interceptRegistrationId
    ZVAL_LONG( &interceptRegistrationIdAsZval, interceptRegistrationId );
    phpPartArgs[ 0 ] = interceptRegistrationIdAsZval;

    // The second argument to PHP part's interceptedCallPreHook() is $thisObj
    if ( Z_TYPE( execute_data->This ) == IS_UNDEF )
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
                    ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_PHP_PART_INTERCEPTED_CALL_PRE_HOOK_FUNC )
                    , interceptedCallArgsCount + 2
                    , phpPartArgs
                    , /* out */ &preHookRetVal ) );
    ELASTIC_APM_LOG_TRACE( "Successfully finished call to PHP part. Return value type: %u", Z_TYPE_P( &preHookRetVal ) );

    if ( Z_TYPE( preHookRetVal ) != IS_FALSE && Z_TYPE( preHookRetVal ) != IS_TRUE )
    {
        ELASTIC_APM_LOG_ERROR( "Call to PHP part returned value that is not bool. Return value type: %u", Z_TYPE_P( &preHookRetVal ) );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }
    shouldCallPostHook = ( Z_TYPE( preHookRetVal ) == IS_TRUE );

    resultCode = resultSuccess;

    finally:
    zval_dtor( &interceptRegistrationIdAsZval );

    ELASTIC_APM_LOG_TRACE_RESULT_CODE_FUNCTION_EXIT();
    ELASTIC_APM_UNUSED( resultCode );
    return shouldCallPostHook;

    failure:
    goto finally;
}

void tracerPhpPartInterceptedCallPostHook( uint32_t dbgInterceptRegistrationId, zval* interceptedCallRetValOrThrown )
{
    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY_MSG( "dbgInterceptRegistrationId: %u; interceptedCallRetValOrThrown type: %u"
                                              , dbgInterceptRegistrationId, Z_TYPE_P( interceptedCallRetValOrThrown ) );

    ResultCode resultCode;
    zval phpPartArgs[ 2 ];

    // The first argument to PHP part's interceptedCallPostHook() is $hasExitedByException (bool)
    ZVAL_FALSE( &( phpPartArgs[ 0 ] ) );

    // The second argument to PHP part's interceptedCallPreHook() is $returnValueOrThrown (mixed|Throwable)
    phpPartArgs[ 1 ] = *interceptedCallRetValOrThrown;

    ELASTIC_APM_CALL_IF_FAILED_GOTO(
            callPhpFunctionRetVoid(
                    ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_PHP_PART_INTERCEPTED_CALL_POST_HOOK_FUNC )
                    , ELASTIC_APM_STATIC_ARRAY_SIZE( phpPartArgs )
                    , phpPartArgs ) );
    ELASTIC_APM_LOG_TRACE( "Successfully finished call to PHP part" );

    resultCode = resultSuccess;

    finally:

    ELASTIC_APM_LOG_TRACE_RESULT_CODE_FUNCTION_EXIT_MSG( "dbgInterceptRegistrationId: %u; interceptedCallRetValOrThrown type: %u."
                                                         , dbgInterceptRegistrationId, Z_TYPE_P( interceptedCallRetValOrThrown ) );
    ELASTIC_APM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}

void tracerPhpPartInterceptedCallEmptyMethod()
{
    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY();

    ResultCode resultCode;
    zval phpPartDummyArgs[ 1 ];
    ZVAL_UNDEF( &( phpPartDummyArgs[ 0 ] ) );

    ELASTIC_APM_CALL_IF_FAILED_GOTO(
            callPhpFunctionRetVoid(
                    ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_PHP_PART_EMPTY_METHOD_FUNC )
                    , 0 /* <- argsCount */
                    , phpPartDummyArgs ) );
    ELASTIC_APM_LOG_TRACE( "Successfully finished call to PHP part" );

    resultCode = resultSuccess;

    finally:

    ELASTIC_APM_LOG_TRACE_RESULT_CODE_FUNCTION_EXIT();
    ELASTIC_APM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}
