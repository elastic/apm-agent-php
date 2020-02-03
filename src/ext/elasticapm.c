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

// external libraries
#include <php.h>
#include <SAPI.h>
#include <Zend/zend_exceptions.h>
#include <ext/spl/spl_exceptions.h>

#include "php_elasticapm.h"
#include "lifecycle.h"
#include "supportability.h"
#include "public_api.h"


ZEND_DECLARE_MODULE_GLOBALS(elasticapm)

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
    STD_PHP_INI_BOOLEAN( "elasticapm.enable", "1", PHP_INI_ALL, OnUpdateBool, enable, Config, ELASTICAPM_GLOBAL_STATE_CONFIG())
    STD_PHP_INI_ENTRY( "elasticapm.host", "localhost:8200", PHP_INI_ALL, OnUpdateString, host, Config, ELASTICAPM_GLOBAL_STATE_CONFIG())
    STD_PHP_INI_ENTRY_EX( "elasticapm.secret_token", "", PHP_INI_ALL, OnUpdateString, secret_token, Config, ELASTICAPM_GLOBAL_STATE_CONFIG(), displaySecretIniValue)
    STD_PHP_INI_ENTRY( "elasticapm.service_name", "", PHP_INI_ALL, OnUpdateString, service_name, Config, ELASTICAPM_GLOBAL_STATE_CONFIG())
    STD_PHP_INI_ENTRY( "elasticapm.log", "", PHP_INI_ALL, OnUpdateString, log, Config, ELASTICAPM_GLOBAL_STATE_CONFIG())
    STD_PHP_INI_ENTRY( "elasticapm.log_level", "0", PHP_INI_ALL, OnUpdateLong, log_level, Config, ELASTICAPM_GLOBAL_STATE_CONFIG())
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

PHP_FUNCTION(elasticApmGetCurrentTransactionId)
{
    RETURN_STRING( getCurrentTransactionId() )
}

PHP_FUNCTION(elasticApmGetCurrentTraceId)
{
    RETURN_STRING( getCurrentTraceId() )
}

static const zend_function_entry elasticapm_functions[] =
{
    PHP_FE(elasticApmGetCurrentTransactionId, NULL)
	PHP_FE(elasticApmGetCurrentTraceId, NULL)
	PHP_FE_END
};

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
