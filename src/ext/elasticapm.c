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

#include "php_elasticapm.h"

// external libraries
#include <php_ini.h>
#include <Zend/zend_types.h>
#include <Zend/zend_ini.h>

#include "lifecycle.h"
#include "supportability.h"
#include "internal_php_API.h"


ZEND_DECLARE_MODULE_GLOBALS( elasticapm )

#ifndef ZEND_PARSE_PARAMETERS_NONE
#   define ZEND_PARSE_PARAMETERS_NONE() \
        ZEND_PARSE_PARAMETERS_START(0, 0) \
        ZEND_PARSE_PARAMETERS_END()
#endif

static inline ZEND_RESULT_CODE resultCodeToZend( ResultCode resultCode )
{
    if ( resultCode == resultSuccess ) return SUCCESS;
    return FAILURE;
}

/* {{{ PHP_RINIT_FUNCTION
 */
PHP_RINIT_FUNCTION(elasticapm)
{
    return resultCodeToZend( elasticApmRequestInit() );
}
/* }}} */

/* {{{ PHP_RSHUTDOWN_FUNCTION
 */
PHP_RSHUTDOWN_FUNCTION(elasticapm)
{
    return resultCodeToZend( elasticApmRequestShutdown() );
}
/* }}} */

/* {{{ PHP_MINFO_FUNCTION
 */
PHP_MINFO_FUNCTION(elasticapm)
{
    elasticapmModuleInfo( zend_module );
}
/* }}} */

#define ELASTICAPM_GLOBAL_STATE_CONFIG() (ZEND_MODULE_GLOBALS_ACCESSOR(elasticapm, state).config)

PHP_INI_BEGIN()
    STD_PHP_INI_BOOLEAN( "elasticapm.enabled", ENABLED_CONFIG_DEFAULT_STR_VALUE, PHP_INI_ALL, OnUpdateBool, enabled, Config, ELASTICAPM_GLOBAL_STATE_CONFIG() )
    STD_PHP_INI_ENTRY( "elasticapm.serverUrl", SERVER_URL_CONFIG_DEFAULT_VALUE, PHP_INI_ALL, OnUpdateString, serverUrl, Config, ELASTICAPM_GLOBAL_STATE_CONFIG() )
    STD_PHP_INI_ENTRY_EX( "elasticapm.secretToken", SECRET_TOKEN_CONFIG_DEFAULT_VALUE, PHP_INI_ALL, OnUpdateString, secretToken, Config, ELASTICAPM_GLOBAL_STATE_CONFIG(), displaySecretIniValue )
    STD_PHP_INI_ENTRY( "elasticapm.service_name", SERVICE_NAME_CONFIG_DEFAULT_VALUE, PHP_INI_ALL, OnUpdateString, serviceName, Config, ELASTICAPM_GLOBAL_STATE_CONFIG() )
    STD_PHP_INI_ENTRY( "elasticapm.log_file", LOG_CONFIG_DEFAULT_VALUE, PHP_INI_ALL, OnUpdateString, logFile, Config, ELASTICAPM_GLOBAL_STATE_CONFIG() )
    STD_PHP_INI_ENTRY( "elasticapm.log_level", LOG_LEVEL_CONFIG_DEFAULT_STR_VALUE, PHP_INI_ALL, OnUpdateLong, logLevel, Config, ELASTICAPM_GLOBAL_STATE_CONFIG() )
PHP_INI_END()

void registerElasticApmIniEntries( int module_number)
{
    REGISTER_INI_ENTRIES();
}

void unregisterElasticApmIniEntries( int module_number)
{
    UNREGISTER_INI_ENTRIES();
}

PHP_MINIT_FUNCTION(elasticapm)
{
    return resultCodeToZend( elasticApmModuleInit( type, module_number ) );
}

PHP_MSHUTDOWN_FUNCTION(elasticapm)
{
    return resultCodeToZend( elasticApmModuleShutdown( type, module_number ) );
}

/* {{{ bool elasticapm_is_enabled()
 */
PHP_FUNCTION( elasticapm_is_enabled )
{
    ZEND_PARSE_PARAMETERS_NONE();

    RETURN_BOOL( isEnabled() )
}
/* }}} */

/* {{{ string elasticapm_get_current_transaction_id()
 */
PHP_FUNCTION( elasticapm_get_current_transaction_id )
{
    const char* const retVal = getCurrentTransactionId();

    ZEND_PARSE_PARAMETERS_NONE();

    if ( retVal == NULL ) RETURN_NULL()
    RETURN_STRING( retVal )
}
/* }}} */

/* {{{ string elasticapm_get_current_trace_id()
 */
PHP_FUNCTION( elasticapm_get_current_trace_id )
{
    const char* const retVal = getCurrentTraceId();

    ZEND_PARSE_PARAMETERS_NONE();

    if ( retVal == NULL ) RETURN_NULL()
    RETURN_STRING( retVal )
}
/* }}} */

/* {{{ arginfo
 */
ZEND_BEGIN_ARG_INFO(elasticapm_no_paramters_arginfo, 0)
ZEND_END_ARG_INFO()
/* }}} */

/* {{{ elasticapm_functions[]
 */
static const zend_function_entry elasticapm_functions[] =
{
    PHP_FE( elasticapm_is_enabled, elasticapm_no_paramters_arginfo )
    PHP_FE( elasticapm_get_current_transaction_id, elasticapm_no_paramters_arginfo )
    PHP_FE( elasticapm_get_current_trace_id, elasticapm_no_paramters_arginfo )
    PHP_FE_END
};
/* }}} */

/* {{{ elasticapm_module_entry
 */
zend_module_entry elasticapm_module_entry = {
	STANDARD_MODULE_HEADER,
	"elasticapm",					/* Extension name */
	elasticapm_functions,			/* zend_function_entry */
	PHP_MINIT(elasticapm),		    /* PHP_MINIT - Module initialization */
	PHP_MSHUTDOWN(elasticapm),		/* PHP_MSHUTDOWN - Module shutdown */
	PHP_RINIT(elasticapm),			/* PHP_RINIT - Request initialization */
	PHP_RSHUTDOWN(elasticapm),		/* PHP_RSHUTDOWN - Request shutdown */
	PHP_MINFO(elasticapm),			/* PHP_MINFO - Module info */
	PHP_ELASTICAPM_VERSION,		    /* Version */
	PHP_MODULE_GLOBALS(elasticapm), /* PHP_MODULE_GLOBALS */
	NULL, 					        /* PHP_GINIT */
	NULL,		                    /* PHP_GSHUTDOWN */
	NULL,
	STANDARD_MODULE_PROPERTIES_EX
};
/* }}} */

#ifdef COMPILE_DL_ELASTICAPM
#   ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
#   endif
ZEND_GET_MODULE(elasticapm)
#endif
