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

#include "supportability.h"

#include <php.h>
#include <ext/standard/info.h>
#include <SAPI.h>

#include "php_elasticapm.h"
#include "log.h"
#include "utils.h"

void elasticapmModuleInfo( zend_module_entry* zend_module )
{
    LOG_FUNCTION_ENTRY();

    const Config* const config = &getGlobalState()->config;

    php_info_print_table_start();
    php_info_print_table_header( 2, "Enabled", config->enabled ? "true" : "FALSE" );
    php_info_print_table_row( 2, "Version", PHP_ELASTICAPM_VERSION );
    php_info_print_table_end();

    DISPLAY_INI_ENTRIES();

    LOG_FUNCTION_EXIT();
}

static const zend_string* iniEntryValue( zend_ini_entry* iniEntry, int type )
{
    return ( type == ZEND_INI_DISPLAY_ORIG ) ? ( iniEntry->modified ? iniEntry->orig_value : iniEntry->value ) : iniEntry->value;
}

void displaySecretIniValue( zend_ini_entry* iniEntry, int type )
{
    const char* const redacted = "***";
    const char* const noValue = "no value";

    const char* const valueToPrint = isNullOrEmtpyZstr( iniEntryValue( iniEntry, type ) ) ? noValue : redacted;

    php_printf( sapi_module.phpinfo_as_text ? "%s" : "<i>%s</i>", valueToPrint );
}
