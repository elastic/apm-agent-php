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
#include "elastic_apm_API.h"
#include "ConfigSnapshot.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_C_TO_PHP

#define ELASTIC_APM_PHP_PART_FUNC_PREFIX "\\Elastic\\Apm\\Impl\\AutoInstrument\\PhpPartFacade::"
#define ELASTIC_APM_PHP_PART_BOOTSTRAP_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "bootstrap"
#define ELASTIC_APM_PHP_PART_SHUTDOWN_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "shutdown"
#define ELASTIC_APM_PHP_PART_INTERNAL_FUNC_CALL_PRE_HOOK_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "internalFuncCallPreHook"
#define ELASTIC_APM_PHP_PART_INTERNAL_FUNC_CALL_POST_HOOK_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "internalFuncCallPostHook"
#define ELASTIC_APM_PHP_PART_EMPTY_METHOD_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "emptyMethod"
#define ELASTIC_APM_PHP_PART_AST_INSTRUMENTATION_PRE_HOOK_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "astInstrumentationPreHook"
#define ELASTIC_APM_PHP_PART_AST_INSTRUMENTATION_DIRECT_CALL_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "astInstrumentationDirectCall"

enum TracerPhpPartState
{
    tracerPhpPartState_before_bootstrap,
    tracerPhpPartState_after_bootstrap,
    tracerPhpPartState_after_shutdown,
    tracerPhpPartState_failed,

    numberOfTracerPhpPartState
};
typedef enum TracerPhpPartState TracerPhpPartState;

StringView tracerPhpPartStateNames[ numberOfTracerPhpPartState ] =
{
    ELASTIC_APM_ENUM_NAMES_ARRAY_PAIR( tracerPhpPartState_before_bootstrap ),
    ELASTIC_APM_ENUM_NAMES_ARRAY_PAIR( tracerPhpPartState_after_bootstrap ),
    ELASTIC_APM_ENUM_NAMES_ARRAY_PAIR( tracerPhpPartState_after_shutdown ),
    ELASTIC_APM_ENUM_NAMES_ARRAY_PAIR( tracerPhpPartState_failed ),
};

#define ELASTIC_APM_UNKNOWN_TRACER_PHP_PART_STATE_AS_STRING "<UNKNOWN TracerPhpPartState>"

static inline
bool isValidTracerPhpPartState( TracerPhpPartState value )
{
    return ( tracerPhpPartState_before_bootstrap <= value ) && ( value < numberOfTracerPhpPartState );
}

static inline
String tracerPhpPartStateToString( TracerPhpPartState value )
{
    if ( isValidTracerPhpPartState( value ) )
    {
        return tracerPhpPartStateNames[ value ].begin;
    }
    return ELASTIC_APM_UNKNOWN_TRACER_PHP_PART_STATE_AS_STRING;
}

static TracerPhpPartState g_tracerPhpPartState = numberOfTracerPhpPartState;

bool switchTracerPhpPartStateToFailed( String reason, String dbgCalledFromFunc )
{
    if ( g_tracerPhpPartState == tracerPhpPartState_failed )
    {
        return false;
    }

    ELASTIC_APM_LOG_ERROR( "Switching tracer PHP part state to failed; reason: %s, current state: %s, called from %s"
                           , reason, tracerPhpPartStateToString( g_tracerPhpPartState ), dbgCalledFromFunc );

    g_tracerPhpPartState = tracerPhpPartState_failed;
    return true;
}

ResultCode bootstrapTracerPhpPart( const ConfigSnapshot* config, const TimePoint* requestInitStartTime )
{
    ResultCode resultCode;
    bool shouldRevertLoadingAgentPhpCode = false;
    bool bootstrapTracerPhpPartRetVal;
    zval maxEnabledLevel;
    ZVAL_UNDEF( &maxEnabledLevel );
    zval requestInitStartTimeZval;
    ZVAL_UNDEF( &requestInitStartTimeZval );

    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "config->bootstrapPhpPartFile: %s, g_tracerPhpPartState: %s"
                                              , streamUserString( config->bootstrapPhpPartFile, &txtOutStream ), tracerPhpPartStateToString( g_tracerPhpPartState ) );
    textOutputStreamRewind( &txtOutStream );

    if ( g_tracerPhpPartState != tracerPhpPartState_before_bootstrap )
    {
        switchTracerPhpPartStateToFailed( /* reason */ "Unexpected current tracer PHP part state", __FUNCTION__ );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if ( config->bootstrapPhpPartFile == NULL )
    {
        GetConfigManagerOptionMetadataResult getMetaRes;
        getConfigManagerOptionMetadata( getGlobalTracer()->configManager, optionId_bootstrapPhpPartFile, &getMetaRes );
        switchTracerPhpPartStateToFailed( /* reason */ streamPrintf( &txtOutStream, "Configuration option `%s' is not set", getMetaRes.optName ), __FUNCTION__ );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    elasticApmBeforeLoadingAgentPhpCode();
    shouldRevertLoadingAgentPhpCode = true;

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

    g_tracerPhpPartState = tracerPhpPartState_after_bootstrap;
    resultCode = resultSuccess;

    finally:
    zval_dtor( &requestInitStartTimeZval );
    zval_dtor( &maxEnabledLevel );
    if ( shouldRevertLoadingAgentPhpCode )
    {
        elasticApmAfterLoadingAgentPhpCode();
    }
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    switchTracerPhpPartStateToFailed( /* reason */ "Failed to bootstrap tracer PHP part", __FUNCTION__ );
    goto finally;
}

void shutdownTracerPhpPart()
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    ResultCode resultCode;

    if ( g_tracerPhpPartState != tracerPhpPartState_after_bootstrap )
    {
        switchTracerPhpPartStateToFailed( /* reason */ "Unexpected current tracer PHP part state", __FUNCTION__ );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( callPhpFunctionRetVoid(
            ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_PHP_PART_SHUTDOWN_FUNC )
            , /* argsCount */ 0
            , /* args */ NULL ) );

    g_tracerPhpPartState = tracerPhpPartState_after_shutdown;
    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    ELASTIC_APM_UNUSED( resultCode );
    return;

    failure:
    switchTracerPhpPartStateToFailed( /* reason */ "Failed to shut down tracer PHP part", __FUNCTION__ );
    goto finally;
}

static uint32_t g_maxInterceptedCallArgsCount = 100;

bool tracerPhpPartInternalFuncCallPreHook( uint32_t interceptRegistrationId, zend_execute_data* execute_data )
{
    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY_MSG( "interceptRegistrationId: %u", interceptRegistrationId );

    ResultCode resultCode;
    zval preHookRetVal;
    ZVAL_UNDEF( &preHookRetVal );
    bool shouldCallPostHook = false;
    zval interceptRegistrationIdAsZval;
    ZVAL_UNDEF( &interceptRegistrationIdAsZval );
    zval phpPartArgs[ g_maxInterceptedCallArgsCount + 2 ];

    if ( g_tracerPhpPartState != tracerPhpPartState_after_bootstrap )
    {
        switchTracerPhpPartStateToFailed( /* reason */ "Unexpected current tracer PHP part state", __FUNCTION__ );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

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
    getArgsFromZendExecuteData( execute_data, g_maxInterceptedCallArgsCount, &( phpPartArgs[ 2 ] ), &interceptedCallArgsCount );
    ELASTIC_APM_CALL_IF_FAILED_GOTO(
            callPhpFunctionRetZval(
                    ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_PHP_PART_INTERNAL_FUNC_CALL_PRE_HOOK_FUNC )
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
    switchTracerPhpPartStateToFailed( /* reason */ "Failed to call tracer PHP part", __FUNCTION__ );
    goto finally;
}

void tracerPhpPartInternalFuncCallPostHook( uint32_t dbgInterceptRegistrationId, zval* interceptedCallRetValOrThrown )
{
    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY_MSG( "dbgInterceptRegistrationId: %u; interceptedCallRetValOrThrown type: %u"
                                              , dbgInterceptRegistrationId, Z_TYPE_P( interceptedCallRetValOrThrown ) );

    ResultCode resultCode;
    zval phpPartArgs[ 2 ];

    if ( g_tracerPhpPartState != tracerPhpPartState_after_bootstrap )
    {
        switchTracerPhpPartStateToFailed( /* reason */ "Unexpected current tracer PHP part state", __FUNCTION__ );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    // The first argument to PHP part's interceptedCallPostHook() is $hasExitedByException (bool)
    ZVAL_FALSE( &( phpPartArgs[ 0 ] ) );

    // The second argument to PHP part's interceptedCallPreHook() is $returnValueOrThrown (mixed|Throwable)
    phpPartArgs[ 1 ] = *interceptedCallRetValOrThrown;

    ELASTIC_APM_CALL_IF_FAILED_GOTO(
            callPhpFunctionRetVoid(
                    ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_PHP_PART_INTERNAL_FUNC_CALL_POST_HOOK_FUNC )
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
    switchTracerPhpPartStateToFailed( /* reason */ "Failed to call tracer PHP part", __FUNCTION__ );
    goto finally;
}

void tracerPhpPartInterceptedCallEmptyMethod()
{
    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY();

    ResultCode resultCode;
    zval phpPartDummyArgs[ 1 ];
    ZVAL_UNDEF( &( phpPartDummyArgs[ 0 ] ) );

    if ( g_tracerPhpPartState != tracerPhpPartState_after_bootstrap )
    {
        switchTracerPhpPartStateToFailed( /* reason */ "Unexpected current tracer PHP part state", __FUNCTION__ );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

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
    switchTracerPhpPartStateToFailed( /* reason */ "Failed to call tracer PHP part", __FUNCTION__ );
    goto finally;
}

void tracerPhpPartLogArguments( LogLevel logLevel, uint32_t argsCount, zval args[] )
{
    if ( maxEnabledLogLevel() < logLevel )
    {
        return;
    }

    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_FOR_EACH_INDEX( i, argsCount )
    {
        ELASTIC_APM_LOG_WITH_LEVEL( logLevel, "Argument #%u: %s", (unsigned)i, streamZVal( &( args[ i ] ), &txtOutStream ) );
    }
}

void tracerPhpPartForwardCall( StringView phpFuncName, zend_execute_data* execute_data, /* out */ zval* retVal, String dbgCalledFrom )
{
    ResultCode resultCode = resultFailure;
    ZVAL_NULL(retVal);
    uint32_t callArgsCount;
    zval callArgs[ g_maxInterceptedCallArgsCount ];

    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY_MSG( "phpFuncName: %s, dbgCalledFrom: %s", phpFuncName.begin, dbgCalledFrom );

    if ( g_tracerPhpPartState != tracerPhpPartState_after_bootstrap )
    {
        if (switchTracerPhpPartStateToFailed( /* reason */ "Unexpected current tracer PHP part state", __FUNCTION__ )) {
            ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
        } else { // no need to print same error message from function - state can't go back to after_bootstrap
            return;
        }
    }

    getArgsFromZendExecuteData( execute_data, g_maxInterceptedCallArgsCount, &( callArgs[ 0 ] ), &callArgsCount );
    tracerPhpPartLogArguments( logLevel_trace, callArgsCount, callArgs );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( callPhpFunctionRetZval( phpFuncName, callArgsCount, callArgs, /* out */ retVal ) );

    resultCode = resultSuccess;
    finally:

    ELASTIC_APM_LOG_TRACE_RESULT_CODE_FUNCTION_EXIT_MSG( "retVal type: %s (type ID as int: %d)", zend_get_type_by_const( (int)Z_TYPE_P( retVal ) ), (int)Z_TYPE_P( retVal ) );
    ELASTIC_APM_UNUSED( resultCode );
    return;

    failure:
    switchTracerPhpPartStateToFailed( /* reason */ "Failed to call tracer PHP part", __FUNCTION__ );
    ZVAL_NULL( retVal );
    goto finally;
}

void tracerPhpPartAstInstrumentationCallPreHook( zend_execute_data* execute_data, zval* return_value )
{
    tracerPhpPartForwardCall( ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_PHP_PART_AST_INSTRUMENTATION_PRE_HOOK_FUNC ), execute_data, /* out */ return_value, __FUNCTION__ );
}

void tracerPhpPartAstInstrumentationDirectCall( zend_execute_data* execute_data )
{
    zval unusedRetVal;
    tracerPhpPartForwardCall( ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_PHP_PART_AST_INSTRUMENTATION_DIRECT_CALL_FUNC ), execute_data, /* out */ &unusedRetVal, __FUNCTION__ );
}

void tracerPhpPartOnRequestInitSetInitialTracerState() {
    g_tracerPhpPartState = tracerPhpPartState_before_bootstrap;
}

ResultCode tracerPhpPartOnRequestInit( const ConfigSnapshot* config, const TimePoint* requestInitStartTime )
{
    return bootstrapTracerPhpPart( config, requestInitStartTime );
}

void tracerPhpPartOnRequestShutdown()
{
    shutdownTracerPhpPart();
}
