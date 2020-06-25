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

#ifdef HAVE_CONFIG_H
# include "config.h"
#endif
#include "php_elastic_apm.h"
// external libraries
#include <php_ini.h>
#include <zend_types.h>
#include "lifecycle.h"
#include "supportability_zend.h"
#include "elastic_apm_API.h"
#include "ConfigManager.h"
#include "elastic_apm_assert.h"

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
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    elasticApmRequestInit();
    return SUCCESS;
}
/* }}} */

/* {{{ PHP_RSHUTDOWN_FUNCTION
 */
PHP_RSHUTDOWN_FUNCTION(elastic_apm)
{
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    elasticApmRequestShutdown();
    return SUCCESS;
}
/* }}} */

/* {{{ PHP_MINFO_FUNCTION
 */
PHP_MINFO_FUNCTION(elastic_apm)
{
    elasticApmModuleInfo( zend_module );
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
    #ifdef PHP_WIN32
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_ALLOW_ABORT_DIALOG )
    #endif
    ELASTIC_APM_NOT_RELOADABLE_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_ABORT_ON_MEMORY_LEAK )
    #if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_ASSERT_LEVEL )
    #endif
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_BOOTSTRAP_PHP_PART_FILE )
    ELASTIC_APM_NOT_RELOADABLE_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_ENABLED )
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
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_SECRET_TOKEN )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_SERVER_CONNECT_TIMEOUT )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_SERVER_URL )
    ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_SERVICE_NAME )
PHP_INI_END()

#undef ELASTIC_APM_INI_ENTRY_IMPL
#undef ELASTIC_APM_INI_ENTRY
#undef ELASTIC_APM_NOT_RELOADABLE_INI_ENTRY
#undef ELASTIC_APM_SECRET_INI_ENTRY

ResultCode registerElasticApmIniEntries( int module_number, IniEntriesRegistrationState* iniEntriesRegistrationState )
{
    ELASTIC_APM_ASSERT_VALID_PTR( iniEntriesRegistrationState );

    ResultCode resultCode;
    const ConfigManager* const cfgManager = getGlobalTracer()->configManager;

    resultCode = zendToResultCode( (ZEND_RESULT_CODE)REGISTER_INI_ENTRIES() );
    if ( resultCode != resultSuccess )
    {
        ELASTIC_APM_LOG_ERROR( "REGISTER_INI_ENTRIES(...) failed. resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
        goto failure;
    }
    iniEntriesRegistrationState->entriesRegistered = true;

    ELASTIC_APM_FOR_EACH_OPTION_ID( optId )
    {
        GetConfigManagerOptionMetadataResult getMetaRes;
        getConfigManagerOptionMetadata( cfgManager, optId, &getMetaRes );
        if ( ! getMetaRes.isSecret ) continue;
        resultCode = zend_ini_register_displayer( (char*) ( getMetaRes.iniName.begin )
                                                  , (uint32_t) ( getMetaRes.iniName.length )
                                                  , displaySecretIniValue );
        if ( resultCode != resultSuccess )
        {
            ELASTIC_APM_LOG_ERROR( "REGISTER_INI_DISPLAYER(...) failed. resultCode: %s (%d). iniName: %.*s."
                                  , resultCodeToString( resultCode ), resultCode
                                  , (int) getMetaRes.iniName.length, getMetaRes.iniName.begin );
            goto failure;
        }
    }

    resultCode = resultSuccess;

    finally:
    return resultCode;

    failure:
    unregisterElasticApmIniEntries( module_number, iniEntriesRegistrationState );
    goto finally;
}

void unregisterElasticApmIniEntries( int module_number, IniEntriesRegistrationState* iniEntriesRegistrationState )
{
    if ( iniEntriesRegistrationState->entriesRegistered )
    {
        UNREGISTER_INI_ENTRIES();
        iniEntriesRegistrationState->entriesRegistered = false;
    }
}

PHP_MINIT_FUNCTION(elastic_apm)
{
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_LOG_LEVEL_NOT_SET", logLevel_not_set, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_LOG_LEVEL_OFF", logLevel_off, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_LOG_LEVEL_CRITICAL", logLevel_critical, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_LOG_LEVEL_ERROR", logLevel_error, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_LOG_LEVEL_WARNING", logLevel_warning, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_LOG_LEVEL_NOTICE", logLevel_notice, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_LOG_LEVEL_INFO", logLevel_info, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_LOG_LEVEL_DEBUG", logLevel_debug, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_LOG_LEVEL_TRACE", logLevel_trace, CONST_CS|CONST_PERSISTENT );

    REGISTER_LONG_CONSTANT( "ELASTIC_APM_ASSERT_LEVEL_NOT_SET", assertLevel_not_set, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_ASSERT_LEVEL_OFF", assertLevel_off, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_ASSERT_LEVEL_O_1", assertLevel_O_1, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_ASSERT_LEVEL_O_N", assertLevel_O_n, CONST_CS|CONST_PERSISTENT );
    REGISTER_LONG_CONSTANT( "ELASTIC_APM_ASSERT_LEVEL_ALL", assertLevel_all, CONST_CS|CONST_PERSISTENT );

    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    elasticApmModuleInit( type, module_number );
    return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(elastic_apm)
{
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    elasticApmModuleShutdown( type, module_number );
    return SUCCESS;
}

/* {{{ bool elastic_apm_is_enabled()
 */
PHP_FUNCTION( elastic_apm_is_enabled )
{
    ZEND_PARSE_PARAMETERS_NONE();

    RETURN_BOOL( elasticApmIsEnabled() )
}
/* }}} */

/* {{{ elastic_apm_get_config_option_by_name( string $optionName ): mixed
 */
PHP_FUNCTION( elastic_apm_get_config_option_by_name )
{
    char* optionName = NULL;
    size_t optionNameLength = 0;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING( optionName, optionNameLength )
    ZEND_PARSE_PARAMETERS_END();

    if ( elasticApmGetConfigOption( optionName, return_value ) != resultSuccess ) RETURN_NULL()
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
    char* className = NULL;
    size_t classNameLength = 0;
    char* methodName = NULL;
    size_t methodNameLength = 0;
    uint32_t interceptRegistrationId;

    ZEND_PARSE_PARAMETERS_START( /* min_num_args: */ 2, /* max_num_args: */ 2 )
    Z_PARAM_STRING( className, classNameLength )
    Z_PARAM_STRING( methodName, methodNameLength )
    ZEND_PARSE_PARAMETERS_END();

    if ( elasticApmInterceptCallsToInternalMethod( className, methodName, &interceptRegistrationId ) != resultSuccess )
        RETURN_LONG( -1 )

    RETURN_LONG( interceptRegistrationId )
}
/* }}} */

ZEND_BEGIN_ARG_INFO_EX( elastic_apm_intercept_calls_to_internal_function_arginfo, /* _unused */ 0, /* return_reference: */ 0, /* required_num_args: */ 1 )
                ZEND_ARG_TYPE_INFO( /* pass_by_ref: */ 0, /* name */ functionName, IS_STRING, /* allow_null: */ 0 )
ZEND_END_ARG_INFO()
/* {{{ elastic_apm_intercept_calls_to_internal_function( string $className, string $functionName ): int // <- interceptRegistrationId
 */
PHP_FUNCTION( elastic_apm_intercept_calls_to_internal_function )
{
    char* functionName = NULL;
    size_t functionNameLength = 0;
    uint32_t interceptRegistrationId;

    ZEND_PARSE_PARAMETERS_START( /* min_num_args: */ 1, /* max_num_args: */ 1 )
    Z_PARAM_STRING( functionName, functionNameLength )
    ZEND_PARSE_PARAMETERS_END();

    if ( elasticApmInterceptCallsToInternalFunction( functionName, &interceptRegistrationId ) != resultSuccess )
        RETURN_LONG( -1 )

    RETURN_LONG( interceptRegistrationId )
}
/* }}} */

ZEND_BEGIN_ARG_INFO_EX( elastic_apm_call_intercepted_original_arginfo, /* _unused */ 0, /* return_reference: */ 0, /* required_num_args: */ 0 )
                ZEND_ARG_TYPE_INFO( /* pass_by_ref: */ 0, wrapperArgsCount, IS_LONG, /* allow_null: */ 0 )
                ZEND_ARG_TYPE_INFO( /* pass_by_ref: */ 0, wrapperArgs, IS_ARRAY, /* allow_null: */ 0 )
ZEND_END_ARG_INFO()
/* {{{ elastic_apm_call_intercepted_original(): mixed
 */
PHP_FUNCTION( elastic_apm_call_intercepted_original )
{
    zend_long wrapperArgsCount = 0;
    zval* wrapperArgs = NULL;

    elasticApmCallInterceptedOriginal( return_value );
}
/* }}} */

/* {{{ elastic_apm_send_to_server( string $serializedEvents ): bool
 */
PHP_FUNCTION( elastic_apm_send_to_server )
{
    char* serializedMetadata = NULL;
    size_t serializedMetadataLength = 0;
    char* serializedEvents = NULL;
    size_t serializedEventsLength = 0;

    ZEND_PARSE_PARAMETERS_START( /* min_num_args: */ 2, /* max_num_args: */ 2 )
    Z_PARAM_STRING( serializedMetadata, serializedMetadataLength )
    Z_PARAM_STRING( serializedEvents, serializedEventsLength )
    ZEND_PARSE_PARAMETERS_END();

    if ( elasticApmSendToServer( makeStringView( serializedMetadata, serializedMetadataLength )
                                 , makeStringView( serializedEvents, serializedEventsLength ) ) != resultSuccess )
    RETURN_FALSE
    RETURN_TRUE
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
}
/* }}} */

/* {{{ arginfo
 */
ZEND_BEGIN_ARG_INFO(elastic_apm_no_paramters_arginfo, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX( elastic_apm_string_paramter_arginfo, 0, 0, 1 )
                ZEND_ARG_TYPE_INFO( /* pass_by_ref: */ 0, optionName, IS_STRING, /* allow_null: */ 0 )
ZEND_END_ARG_INFO()
/* }}} */

/* {{{ elastic_apm_functions[]
 */
static const zend_function_entry elastic_apm_functions[] =
{
    PHP_FE( elastic_apm_is_enabled, elastic_apm_no_paramters_arginfo )
    PHP_FE( elastic_apm_get_config_option_by_name, elastic_apm_string_paramter_arginfo )
    PHP_FE( elastic_apm_intercept_calls_to_internal_method, elastic_apm_intercept_calls_to_internal_method_arginfo )
    PHP_FE( elastic_apm_intercept_calls_to_internal_function, elastic_apm_intercept_calls_to_internal_function_arginfo )
    PHP_FE( elastic_apm_call_intercepted_original, elastic_apm_call_intercepted_original_arginfo )
    PHP_FE( elastic_apm_send_to_server, elastic_apm_string_paramter_arginfo )
    PHP_FE( elastic_apm_log, elastic_apm_log_arginfo )
    PHP_FE_END
};
/* }}} */

/* {{{ elastic_apm_module_entry
 */
zend_module_entry elastic_apm_module_entry = {
	STANDARD_MODULE_HEADER,
	"elastic_apm",					/* Extension name */
	elastic_apm_functions,			/* zend_function_entry */
	PHP_MINIT(elastic_apm),		    /* PHP_MINIT - Module initialization */
	PHP_MSHUTDOWN(elastic_apm),		/* PHP_MSHUTDOWN - Module shutdown */
	PHP_RINIT(elastic_apm),			/* PHP_RINIT - Request initialization */
	PHP_RSHUTDOWN(elastic_apm),		/* PHP_RSHUTDOWN - Request shutdown */
	PHP_MINFO(elastic_apm),			/* PHP_MINFO - Module info */
	PHP_ELASTIC_APM_VERSION,		    /* Version */
	PHP_MODULE_GLOBALS(elastic_apm), /* PHP_MODULE_GLOBALS */
	NULL, 					        /* PHP_GINIT */
	NULL,		                    /* PHP_GSHUTDOWN */
	NULL,
	STANDARD_MODULE_PROPERTIES_EX
};
/* }}} */

#ifdef COMPILE_DL_ELASTIC_APM
#   ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
#   endif
ZEND_GET_MODULE(elastic_apm)
#endif
