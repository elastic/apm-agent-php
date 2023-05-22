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

#ifdef HAVE_CONFIG_H
# include "config.h"
#endif
#include "php_elastic_apm.h"
// external libraries
#include <php_ini.h>
#include <php.h>
#include <zend_types.h>
#include "constants.h"
#include "lifecycle.h"
#include "supportability_zend.h"
#include "elastic_apm_API.h"
#include "ConfigManager.h"
#include "elastic_apm_assert.h"
#include "elastic_apm_alloc.h"
#include "tracer_PHP_part.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_EXT_INFRA

ZEND_DECLARE_MODULE_GLOBALS( elastic_apm )

Tracer* getGlobalTracer()
{
    return &( ZEND_MODULE_GLOBALS_ACCESSOR( elastic_apm, globalTracer ) );
}

#ifndef ZEND_PARSE_PARAMETERS_NONE
#   define ZEND_PARSE_PARAMETERS_NONE() \
        ZEND_PARSE_PARAMETERS_START(0, 0) \
        ZEND_PARSE_PARAMETERS_END()
#endif

static inline ResultCode zendToResultCode( ZEND_RESULT_CODE zendResultCode )
{
    if ( zendResultCode == SUCCESS ) return resultSuccess;
    return resultFailure;
}

/* {{{ PHP_RINIT_FUNCTION
 */
PHP_RINIT_FUNCTION(elastic_apm)
{
    ResultCode resultCode;

    // We SHOULD NOT log before resetting state if forked because logging might be using thread synchronization
    // which might deadlock in forked child
    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmApiEntered( __FILE__, __LINE__, __FUNCTION__ ) );

    elasticApmRequestInit();

    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    finally:
    return SUCCESS;

    failure:
    goto finally;
}
/* }}} */

/* {{{ PHP_RSHUTDOWN_FUNCTION
 */
PHP_RSHUTDOWN_FUNCTION(elastic_apm)
{
    ResultCode resultCode;

    // We SHOULD NOT log before resetting state if forked because logging might be using thread synchronization
    // which might deadlock in forked child
    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmApiEntered( __FILE__, __LINE__, __FUNCTION__ ) );

    elasticApmRequestShutdown();

    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    finally:
    return SUCCESS;

    failure:
    goto finally;
}
/* }}} */

/* {{{ PHP_MINFO_FUNCTION
 */
PHP_MINFO_FUNCTION(elastic_apm)
{
    ResultCode resultCode;

    // We SHOULD NOT log before resetting state if forked because logging might be using thread synchronization
    // which might deadlock in forked child
    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmApiEntered( __FILE__, __LINE__, __FUNCTION__ ) );

    elasticApmModuleInfo( zend_module );

    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    finally:
    return;

    failure:
    goto finally;
}
/* }}} */

#define ELASTIC_APM_INI_ENTRY_IMPL( optName, isReloadableFlag ) \
    PHP_INI_ENTRY( \
        "elastic_apm." optName \
        , /* default value: */ NULL \
        , isReloadableFlag \
        , /* on_modify (validator): */ NULL )

#define ELASTIC_APM_INI_ENTRY( optName ) ELASTIC_APM_INI_ENTRY_IMPL( optName, PHP_INI_ALL )

#define ELASTIC_APM_NOT_RELOADABLE_INI_ENTRY( optName ) ELASTIC_APM_INI_ENTRY_IMPL( optName, PHP_INI_PERDIR )

PHP_INI_BEGIN()
    ELASTIC_APM_NOT_RELOADABLE_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_ABORT_ON_MEMORY_LEAK )
    #ifdef PHP_WIN32
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_ALLOW_ABORT_DIALOG )
    #endif
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_API_KEY )
    #if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_ASSERT_LEVEL )
    #endif
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_AST_PROCESS_ENABLED )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_AST_PROCESS_DEBUG_DUMP_CONVERTED_BACK_TO_SOURCE )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_AST_PROCESS_DEBUG_DUMP_FOR_PATH_PREFIX )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_AST_PROCESS_DEBUG_DUMP_OUT_DIR )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_ASYNC_BACKEND_COMM )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_BOOTSTRAP_PHP_PART_FILE )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_BREAKDOWN_METRICS )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_CAPTURE_ERRORS )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_DEV_INTERNAL )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_DEV_INTERNAL_BACKEND_COMM_LOG_VERBOSE )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_DISABLE_INSTRUMENTATIONS )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_DISABLE_SEND )
    ELASTIC_APM_NOT_RELOADABLE_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_ENABLED )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_ENVIRONMENT )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_HOSTNAME )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_INTERNAL_CHECKS_LEVEL )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_LOG_FILE )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_FILE )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_STDERR )
    #ifndef PHP_WIN32
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_SYSLOG )
    #endif
    #ifdef PHP_WIN32
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_WIN_SYS_DEBUG )
    #endif
    #if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_MEMORY_TRACKING_LEVEL )
    #endif
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_NON_KEYWORD_STRING_MAX_LENGTH )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_PROFILING_INFERRED_SPANS_ENABLED )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_PROFILING_INFERRED_SPANS_MIN_DURATION )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_PROFILING_INFERRED_SPANS_SAMPLING_INTERVAL )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_SANITIZE_FIELD_NAMES )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_SECRET_TOKEN )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_SERVER_TIMEOUT )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_SERVER_URL )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_SERVICE_NAME )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_SERVICE_NODE_NAME )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_SERVICE_VERSION )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_ENABLED )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_SAME_KIND_MAX_DURATION )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_IGNORE_URLS )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_MAX_SPANS )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_SAMPLE_RATE )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_URL_GROUPS )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_VERIFY_SERVER_CERT )
PHP_INI_END()

#undef ELASTIC_APM_INI_ENTRY_IMPL
#undef ELASTIC_APM_INI_ENTRY
#undef ELASTIC_APM_NOT_RELOADABLE_INI_ENTRY
#undef ELASTIC_APM_SECRET_INI_ENTRY

ResultCode registerElasticApmIniEntries( int type, int module_number, IniEntriesRegistrationState* iniEntriesRegistrationState )
{
    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY_MSG( "module: { type: %d, number: %d }", type, module_number );

    ELASTIC_APM_ASSERT_VALID_PTR( iniEntriesRegistrationState );

    ResultCode resultCode;
    const ConfigManager* const cfgManager = getGlobalTracer()->configManager;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( zendToResultCode( (ZEND_RESULT_CODE)REGISTER_INI_ENTRIES() ) );
    iniEntriesRegistrationState->entriesRegistered = true;

    ELASTIC_APM_FOR_EACH_OPTION_ID( optId )
    {
        GetConfigManagerOptionMetadataResult getMetaRes;
        getConfigManagerOptionMetadata( cfgManager, optId, &getMetaRes );
        if ( ! getMetaRes.isSecret ) continue;
        int zendResultCode = zend_ini_register_displayer( (char*) ( getMetaRes.iniName.begin )
                                                  , (uint32_t) ( getMetaRes.iniName.length )
                                                  , displaySecretIniValue );
        if ( zendResultCode != SUCCESS )
        {
            ELASTIC_APM_LOG_ERROR( "zend_ini_register_displayer() failed with result code: %d; iniName: %.*s."
                                  , zendResultCode
                                  , (int) getMetaRes.iniName.length, getMetaRes.iniName.begin );
            ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
        }
    }

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_LOG_TRACE_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    unregisterElasticApmIniEntries( type, module_number, iniEntriesRegistrationState );
    goto finally;
}

void unregisterElasticApmIniEntries( int type, int module_number, IniEntriesRegistrationState* iniEntriesRegistrationState )
{
    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY_MSG( "module: { type: %d, number: %d }", type, module_number );

    if ( iniEntriesRegistrationState->entriesRegistered )
    {
        UNREGISTER_INI_ENTRIES();
        iniEntriesRegistrationState->entriesRegistered = false;
    }

    ELASTIC_APM_LOG_TRACE_FUNCTION_EXIT();
}

static PHP_GINIT_FUNCTION(elastic_apm)
{
    ELASTIC_APM_LOG_DIRECT_DEBUG( "%s: GINIT called; parent PID: %d", __FUNCTION__, (int)getParentProcessId() );
}

static PHP_GSHUTDOWN_FUNCTION(elastic_apm)
{
    ELASTIC_APM_LOG_DIRECT_DEBUG( "%s: GSHUTDOWN called; parent PID: %d", __FUNCTION__, (int)getParentProcessId() );
}

PHP_MINIT_FUNCTION(elastic_apm)
{
    ResultCode resultCode;

    // We SHOULD NOT log before resetting state if forked because logging might be using thread synchronization
    // which might deadlock in forked child
    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmApiEntered( __FILE__, __LINE__, __FUNCTION__ ) );

    REGISTER_LONG_CONSTANT( "ELASTIC_APM_LOG_LEVEL_NOT_SET", logLevel_not_set, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_LOG_LEVEL_OFF", logLevel_off, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_LOG_LEVEL_CRITICAL", logLevel_critical, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_LOG_LEVEL_ERROR", logLevel_error, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_LOG_LEVEL_WARNING", logLevel_warning, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_LOG_LEVEL_INFO", logLevel_info, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_LOG_LEVEL_DEBUG", logLevel_debug, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_LOG_LEVEL_TRACE", logLevel_trace, CONST_CS|CONST_PERSISTENT );

    REGISTER_LONG_CONSTANT( "ELASTIC_APM_ASSERT_LEVEL_NOT_SET", assertLevel_not_set, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_ASSERT_LEVEL_OFF", assertLevel_off, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_ASSERT_LEVEL_O_1", assertLevel_O_1, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_ASSERT_LEVEL_O_N", assertLevel_O_n, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_ASSERT_LEVEL_ALL", assertLevel_all, CONST_CS|CONST_PERSISTENT );

    REGISTER_LONG_CONSTANT( "ELASTIC_APM_MEMORY_TRACKING_LEVEL_NOT_SET", memoryTrackingLevel_not_set, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_MEMORY_TRACKING_LEVEL_OFF", memoryTrackingLevel_off, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_MEMORY_TRACKING_LEVEL_TOTAL_COUNT_ONLY", memoryTrackingLevel_totalCountOnly, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_MEMORY_TRACKING_LEVEL_EACH_ALLOCATION", memoryTrackingLevel_eachAllocation, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_MEMORY_TRACKING_LEVEL_EACH_ALLOCATION_WITH_STACK_TRACE", memoryTrackingLevel_eachAllocationWithStackTrace, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_MEMORY_TRACKING_LEVEL_ALL", memoryTrackingLevel_all, CONST_CS|CONST_PERSISTENT );

    REGISTER_STRING_CONSTANT( ELASTIC_APM_WORDPRESS_DIRECT_CALL_METHOD_SET_READY_TO_WRAP_FILTER_CALLBACKS_CONST_NAME
                              , ELASTIC_APM_WORDPRESS_DIRECT_CALL_METHOD_SET_READY_TO_WRAP_FILTER_CALLBACKS
                              , CONST_CS | CONST_PERSISTENT );

    elasticApmModuleInit( type, module_number );

    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    finally:
    return SUCCESS;

    failure:
    goto finally;
}

PHP_MSHUTDOWN_FUNCTION(elastic_apm)
{
    ResultCode resultCode;

    // We SHOULD NOT log before resetting state if forked because logging might be using thread synchronization
    // which might deadlock in forked child
    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmApiEntered( __FILE__, __LINE__, __FUNCTION__ ) );

    elasticApmModuleShutdown( type, module_number );

    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    finally:
    return SUCCESS;

    failure:
    goto finally;
}

/* {{{ bool elastic_apm_is_enabled()
 */
PHP_FUNCTION( elastic_apm_is_enabled )
{
    ResultCode resultCode;
    bool retVal = false;

    // We SHOULD NOT log before resetting state if forked because logging might be using thread synchronization
    // which might deadlock in forked child
    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmApiEntered( __FILE__, __LINE__, __FUNCTION__ ) );

    ZEND_PARSE_PARAMETERS_NONE();
    retVal = elasticApmIsEnabled();

    finally:
    RETURN_BOOL( retVal );

    failure:
    goto finally;
}
/* }}} */

ZEND_BEGIN_ARG_INFO_EX( elastic_apm_get_config_option_by_name_arginfo, 0, 0, 1 )
                ZEND_ARG_TYPE_INFO( /* pass_by_ref: */ 0, optionName, IS_STRING, /* allow_null: */ 0 )
ZEND_END_ARG_INFO()

/* {{{ elastic_apm_get_config_option_by_name( string $optionName ): mixed
 */
PHP_FUNCTION( elastic_apm_get_config_option_by_name )
{
    ResultCode resultCode;
    ZVAL_NULL( return_value );

    // We SHOULD NOT log before resetting state if forked because logging might be using thread synchronization
    // which might deadlock in forked child
    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmApiEntered( __FILE__, __LINE__, __FUNCTION__ ) );

    char* optionName = NULL;
    size_t optionNameLength = 0;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING( optionName, optionNameLength )
    ZEND_PARSE_PARAMETERS_END();

    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmGetConfigOption( optionName, /* out */ return_value ) );

    finally:
    return;

    failure:
    goto finally;
}
/* }}} */

/* {{{ elastic_apm_get_number_of_dynamic_config_options(): int
 */
PHP_FUNCTION( elastic_apm_get_number_of_dynamic_config_options )
{
    ResultCode resultCode;
    long retVal = 0;

    // We SHOULD NOT log before resetting state if forked because logging might be using thread synchronization
    // which might deadlock in forked child
    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmApiEntered( __FILE__, __LINE__, __FUNCTION__ ) );

    ZEND_PARSE_PARAMETERS_NONE();

    retVal = elasticApmGetNumberOfDynamicConfigOptions(); // NOLINT(cppcoreguidelines-narrowing-conversions)

    finally:
    RETURN_LONG( retVal );

    failure:
    goto finally;
}
/* }}} */

ZEND_BEGIN_ARG_INFO_EX( elastic_apm_intercept_calls_to_internal_method_arginfo, /* _unused */ 0, /* return_reference: */ 0, /* required_num_args: */ 2 )
                ZEND_ARG_TYPE_INFO( /* pass_by_ref: */ 0, /* name */ className, IS_STRING, /* allow_null: */ 0 )
                ZEND_ARG_TYPE_INFO( /* pass_by_ref: */ 0, /* name */ methodName, IS_STRING, /* allow_null: */ 0 )
ZEND_END_ARG_INFO()
/* {{{ elastic_apm_intercept_calls_to_internal_method( string $className, string $methodName ): int // <- interceptRegistrationId
 */
PHP_FUNCTION( elastic_apm_intercept_calls_to_internal_method )
{
    ResultCode resultCode;
    long retVal = -1;

    // We SHOULD NOT log before resetting state if forked because logging might be using thread synchronization
    // which might deadlock in forked child
    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmApiEntered( __FILE__, __LINE__, __FUNCTION__ ) );

    char* className = NULL;
    size_t classNameLength = 0;
    char* methodName = NULL;
    size_t methodNameLength = 0;
    uint32_t interceptRegistrationId;

    ZEND_PARSE_PARAMETERS_START( /* min_num_args: */ 2, /* max_num_args: */ 2 )
    Z_PARAM_STRING( className, classNameLength )
    Z_PARAM_STRING( methodName, methodNameLength )
    ZEND_PARSE_PARAMETERS_END();

    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmInterceptCallsToInternalMethod( className, methodName, &interceptRegistrationId ) );
    retVal = (long)interceptRegistrationId;

    finally:
    RETURN_LONG( retVal );

    failure:
    goto finally;
}
/* }}} */

ZEND_BEGIN_ARG_INFO_EX( elastic_apm_intercept_calls_to_internal_function_arginfo, /* _unused */ 0, /* return_reference: */ 0, /* required_num_args: */ 1 )
    ZEND_ARG_TYPE_INFO( /* pass_by_ref: */ 0, /* name */ functionName, IS_STRING, /* allow_null: */ 0 )
ZEND_END_ARG_INFO()
/* {{{ elastic_apm_intercept_calls_to_internal_function( string $className, string $functionName ): int // <- interceptRegistrationId
 */
PHP_FUNCTION( elastic_apm_intercept_calls_to_internal_function )
{
    ResultCode resultCode;
    long retVal = -1;

    // We SHOULD NOT log before resetting state if forked because logging might be using thread synchronization
    // which might deadlock in forked child
    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmApiEntered( __FILE__, __LINE__, __FUNCTION__ ) );

    char* functionName = NULL;
    size_t functionNameLength = 0;
    uint32_t interceptRegistrationId;

    ZEND_PARSE_PARAMETERS_START( /* min_num_args: */ 1, /* max_num_args: */ 1 )
    Z_PARAM_STRING( functionName, functionNameLength )
    ZEND_PARSE_PARAMETERS_END();

    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmInterceptCallsToInternalFunction( functionName, &interceptRegistrationId ) );
    retVal = (long)interceptRegistrationId;

    finally:
    RETURN_LONG( retVal );

    failure:
    goto finally;
}
/* }}} */

ZEND_BEGIN_ARG_INFO_EX( elastic_apm_send_to_server_arginfo, /* _unused: */ 0, /* return_reference: */ 0, /* required_num_args: */ 2 )
    ZEND_ARG_TYPE_INFO( /* pass_by_ref: */ 0, userAgentHttpHeader, IS_STRING, /* allow_null: */ 0 )
    ZEND_ARG_TYPE_INFO( /* pass_by_ref: */ 0, serializedEvents, IS_STRING, /* allow_null: */ 0 )
ZEND_END_ARG_INFO()

/* {{{ elastic_apm_send_to_server(
 *          string userAgentHttpHeader,
 *          string $serializedEvents ): bool
 */
PHP_FUNCTION( elastic_apm_send_to_server )
{
    ResultCode resultCode;
    bool retVal = false;

    // We SHOULD NOT log before resetting state if forked because logging might be using thread synchronization
    // which might deadlock in forked child
    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmApiEntered( __FILE__, __LINE__, __FUNCTION__ ) );

    char* userAgentHttpHeader = NULL;
    size_t userAgentHttpHeaderLength = 0;
    char* serializedEvents = NULL;
    size_t serializedEventsLength = 0;

    ZEND_PARSE_PARAMETERS_START( /* min_num_args: */ 2, /* max_num_args: */ 2 )
        Z_PARAM_STRING( userAgentHttpHeader, userAgentHttpHeaderLength )
        Z_PARAM_STRING( serializedEvents, serializedEventsLength )
    ZEND_PARSE_PARAMETERS_END();

    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmSendToServer(
            makeStringView( userAgentHttpHeader, userAgentHttpHeaderLength )
            , makeStringView( serializedEvents, serializedEventsLength ) ) );

    retVal = true;
    finally:
    RETURN_BOOL( retVal );

    failure:
    goto finally;
}
/* }}} */

ZEND_BEGIN_ARG_INFO_EX( elastic_apm_log_arginfo, /* _unused: */ 0, /* return_reference: */ 0, /* required_num_args: */ 7 )
                ZEND_ARG_TYPE_INFO( /* pass_by_ref: */ 0, isForced, IS_LONG, /* allow_null: */ 0 )
                ZEND_ARG_TYPE_INFO( /* pass_by_ref: */ 0, level, IS_LONG, /* allow_null: */ 0 )
                ZEND_ARG_TYPE_INFO( /* pass_by_ref: */ 0, category, IS_STRING, /* allow_null: */ 0 )
                ZEND_ARG_TYPE_INFO( /* pass_by_ref: */ 0, file, IS_STRING, /* allow_null: */ 0 )
                ZEND_ARG_TYPE_INFO( /* pass_by_ref: */ 0, line, IS_LONG, /* allow_null: */ 0 )
                ZEND_ARG_TYPE_INFO( /* pass_by_ref: */ 0, func, IS_STRING, /* allow_null: */ 0 )
                ZEND_ARG_TYPE_INFO( /* pass_by_ref: */ 0, message, IS_STRING, /* allow_null: */ 0 )
ZEND_END_ARG_INFO()

/* {{{ elastic_apm_log(
 *      int $isForced,
 *      int $level,
 *      string $category,
 *      string $file,
 *      int $line,
 *      string $func,
 *      string $message
 *  ): void
 */
PHP_FUNCTION( elastic_apm_log )
{
    ResultCode resultCode;

    // We SHOULD NOT log before resetting state if forked because logging might be using thread synchronization
    // which might deadlock in forked child
    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmApiEntered( __FILE__, __LINE__, __FUNCTION__ ) );

    zend_long isForced = 0;
    zend_long level = 0;
    char* file = NULL;
    size_t fileLength = 0;
    char* category = NULL;
    size_t categoryLength = 0;
    zend_long line = 0;
    char* func = NULL;
    size_t funcLength = 0;
    char* message = NULL;
    size_t messageLength = 0;

    ZEND_PARSE_PARAMETERS_START( /* min_num_args: */ 7, /* max_num_args: */ 7 )
    Z_PARAM_LONG( isForced )
    Z_PARAM_LONG( level )
    Z_PARAM_STRING( category, categoryLength )
    Z_PARAM_STRING( file, fileLength )
    Z_PARAM_LONG( line )
    Z_PARAM_STRING( func, funcLength )
    Z_PARAM_STRING( message, messageLength )
    ZEND_PARSE_PARAMETERS_END();

    logWithLogger(
            getGlobalLogger()
            , /* isForced: */ ( isForced != 0 )
            , /* statementLevel: */ (LogLevel) level
            , /* category: */ makeStringView( category, categoryLength )
            , /* filePath: */ makeStringView( file, fileLength )
            , /* lineNumber: */ (UInt) line
            , /* funcName: */ makeStringView( func, funcLength )
            , /* msgPrintfFmt: */ "%s"
            ,  /* msgPrintfFmtArgs: */ message );

    finally:
    return;

    failure:
    goto finally;
}
/* }}} */

ZEND_BEGIN_ARG_INFO_EX( elastic_apm_get_last_thrown_arginfo, /* _unused */ 0, /* return_reference: */ 0, /* required_num_args: */ 0 )
ZEND_END_ARG_INFO()
/* {{{ elastic_apm_get_last_thrown(): mixed
 */
PHP_FUNCTION( elastic_apm_get_last_thrown )
{
    ResultCode resultCode;
    ZVAL_NULL( /* out */ return_value );

    // We SHOULD NOT log before resetting state if forked because logging might be using thread synchronization
    // which might deadlock in forked child
    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmApiEntered( __FILE__, __LINE__, __FUNCTION__ ) );

    elasticApmGetLastThrown( /* out */ return_value );

    finally:
    return;

    failure:
    goto finally;
}
/* }}} */

ZEND_BEGIN_ARG_INFO_EX( elastic_apm_get_last_php_error_arginfo, /* _unused */ 0, /* return_reference: */ 0, /* required_num_args: */ 0 )
ZEND_END_ARG_INFO()
/* {{{ elastic_apm_get_last_error(): array
 */
PHP_FUNCTION( elastic_apm_get_last_php_error )
{
    ResultCode resultCode;
    ZVAL_NULL( /* out */ return_value );

    // We SHOULD NOT log before resetting state if forked because logging might be using thread synchronization
    // which might deadlock in forked child
    ELASTIC_APM_CALL_IF_FAILED_GOTO( elasticApmApiEntered( __FILE__, __LINE__, __FUNCTION__ ) );

    elasticApmGetLastPhpError( /* out */ return_value );

    finally:
    return;

    failure:
    goto finally;
}
/* }}} */

ZEND_BEGIN_ARG_INFO_EX( elastic_apm_before_loading_agent_php_code_arginfo, /* _unused */ 0, /* return_reference: */ 0, /* required_num_args: */ 0 )
ZEND_END_ARG_INFO()
/* {{{ elastic_apm_before_loading_agent_php_code(): void
 */
PHP_FUNCTION( elastic_apm_before_loading_agent_php_code )
{
    elasticApmBeforeLoadingAgentPhpCode();
}
/* }}} */

ZEND_BEGIN_ARG_INFO_EX( elastic_apm_after_loading_agent_php_code_arginfo, /* _unused */ 0, /* return_reference: */ 0, /* required_num_args: */ 0 )
ZEND_END_ARG_INFO()
/* {{{ elastic_apm_after_loading_agent_php_code(): void
 */
PHP_FUNCTION( elastic_apm_after_loading_agent_php_code )
{
    elasticApmAfterLoadingAgentPhpCode();
}
/* }}} */

ZEND_BEGIN_ARG_INFO_EX( elastic_apm_ast_instrumentation_pre_hook_arginfo, /* _unused */ 0, /* return_reference: */ 0, /* required_num_args: */ 0 )
ZEND_END_ARG_INFO()
/* {{{ elastic_apm_after_loading_agent_php_code(): void
 */
PHP_FUNCTION( elastic_apm_ast_instrumentation_pre_hook )
{
    tracerPhpPartAstInstrumentationCallPreHook( execute_data, return_value );
}
/* }}} */

ZEND_BEGIN_ARG_INFO_EX( elastic_apm_ast_instrumentation_direct_call_arginfo, /* _unused */ 0, /* return_reference: */ 0, /* required_num_args: */ 0 )
ZEND_END_ARG_INFO()
/* {{{ elastic_apm_after_loading_agent_php_code(): void
 */
PHP_FUNCTION( elastic_apm_ast_instrumentation_direct_call )
{
    tracerPhpPartAstInstrumentationDirectCall( execute_data );
}
/* }}} */

/* {{{ arginfo
 */
ZEND_BEGIN_ARG_INFO(elastic_apm_no_paramters_arginfo, 0)
ZEND_END_ARG_INFO()
/* }}} */

/* {{{ elastic_apm_functions[]
 */
static const zend_function_entry elastic_apm_functions[] =
{
    PHP_FE( elastic_apm_is_enabled, elastic_apm_no_paramters_arginfo )
    PHP_FE( elastic_apm_get_config_option_by_name, elastic_apm_get_config_option_by_name_arginfo )
    PHP_FE( elastic_apm_get_number_of_dynamic_config_options, elastic_apm_no_paramters_arginfo )
    PHP_FE( elastic_apm_intercept_calls_to_internal_method, elastic_apm_intercept_calls_to_internal_method_arginfo )
    PHP_FE( elastic_apm_intercept_calls_to_internal_function, elastic_apm_intercept_calls_to_internal_function_arginfo )
    PHP_FE( elastic_apm_send_to_server, elastic_apm_send_to_server_arginfo )
    PHP_FE( elastic_apm_log, elastic_apm_log_arginfo )
    PHP_FE( elastic_apm_get_last_thrown, elastic_apm_get_last_thrown_arginfo )
    PHP_FE( elastic_apm_get_last_php_error, elastic_apm_get_last_php_error_arginfo )
    PHP_FE( elastic_apm_before_loading_agent_php_code, elastic_apm_before_loading_agent_php_code_arginfo )
    PHP_FE( elastic_apm_after_loading_agent_php_code, elastic_apm_after_loading_agent_php_code_arginfo )
    PHP_FE( elastic_apm_ast_instrumentation_pre_hook, elastic_apm_ast_instrumentation_pre_hook_arginfo )
    PHP_FE( elastic_apm_ast_instrumentation_direct_call, elastic_apm_ast_instrumentation_direct_call_arginfo )
    PHP_FE_END
};
/* }}} */

/* {{{ elastic_apm_module_entry
 */
zend_module_entry elastic_apm_module_entry = {
	STANDARD_MODULE_HEADER,
	"elastic_apm",					 /* Extension name */
	elastic_apm_functions,			 /* zend_function_entry */
	PHP_MINIT(elastic_apm),		     /* PHP_MINIT - Module initialization */
	PHP_MSHUTDOWN(elastic_apm),		 /* PHP_MSHUTDOWN - Module shutdown */
	PHP_RINIT(elastic_apm),			 /* PHP_RINIT - Request initialization */
	PHP_RSHUTDOWN(elastic_apm),		 /* PHP_RSHUTDOWN - Request shutdown */
	PHP_MINFO(elastic_apm),			 /* PHP_MINFO - Module info */
	PHP_ELASTIC_APM_VERSION,		 /* Version */
	PHP_MODULE_GLOBALS(elastic_apm), /* PHP_MODULE_GLOBALS */
    PHP_GINIT(elastic_apm), 	     /* PHP_GINIT */
    PHP_GSHUTDOWN(elastic_apm),		 /* PHP_GSHUTDOWN */
	NULL,                            /* post deactivate */
	STANDARD_MODULE_PROPERTIES_EX
};
/* }}} */

#ifdef COMPILE_DL_ELASTIC_APM
#   ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
#   endif
ZEND_GET_MODULE(elastic_apm)
#endif
