--TEST--
Setting configuration options to non-default value (in this case using environment variables)
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=OFF
ELASTIC_APM_ENABLED=not_valid_boolean_value
ELASTIC_APM_LOG_FILE=|:/:\:|
ELASTIC_APM_LOG_LEVEL=not valid log level
ELASTIC_APM_LOG_LEVEL_FILE=not valid log level
ELASTIC_APM_LOG_LEVEL_SYSLOG=not valid log level
ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG=not valid log level
ELASTIC_APM_SECRET_TOKEN=\|<>|/
ELASTIC_APM_SERVER_URL=<\/\/>
ELASTIC_APM_SERVICE_NAME=/\><\/
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

//////////////////////////////////////////////
///////////////  enabled

elasticApmAssertSame("getenv('ELASTIC_APM_ENABLED')", getenv('ELASTIC_APM_ENABLED'), 'not_valid_boolean_value');

elasticApmAssertSame("elasticapm_is_enabled()", elasticapm_is_enabled(), true);

elasticApmAssertSame("elasticapm_get_config_option_by_name('enabled')", elasticapm_get_config_option_by_name('enabled'), true);

//////////////////////////////////////////////
///////////////  log_file

elasticApmAssertSame(
    "getenv('ELASTIC_APM_LOG_FILE')",
    getenv('ELASTIC_APM_LOG_FILE'),
    '|:/:\:|' // getenv returns false when environment variable is set to empty string
);

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_file')", elasticapm_get_config_option_by_name('log_file'), '|:/:\:|');

//////////////////////////////////////////////
///////////////  log_level

elasticApmAssertSame("getenv('ELASTIC_APM_LOG_LEVEL')", getenv('ELASTIC_APM_LOG_LEVEL'), 'not valid log level');

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level')", elasticapm_get_config_option_by_name('log_level'), ELASTICAPM_LOG_LEVEL_NOT_SET);

//////////////////////////////////////////////
///////////////  log_level_file

elasticApmAssertSame("getenv('ELASTIC_APM_LOG_LEVEL_FILE')", getenv('ELASTIC_APM_LOG_LEVEL_FILE'), 'not valid log level');

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_file')", elasticapm_get_config_option_by_name('log_level_file'), ELASTICAPM_LOG_LEVEL_NOT_SET);

//////////////////////////////////////////////
///////////////  log_level_syslog

elasticApmAssertSame("getenv('ELASTIC_APM_LOG_LEVEL_SYSLOG')", getenv('ELASTIC_APM_LOG_LEVEL_SYSLOG'), 'not valid log level');

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_syslog')", elasticapm_get_config_option_by_name('log_level_syslog'), ELASTICAPM_LOG_LEVEL_NOT_SET);
}

//////////////////////////////////////////////
///////////////  log_level_win_sys_debug

elasticApmAssertSame("getenv('ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG')", getenv('ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG'), 'not valid log level');

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_win_sys_debug')", elasticapm_get_config_option_by_name('log_level_win_sys_debug'), ELASTICAPM_LOG_LEVEL_NOT_SET);
}

//////////////////////////////////////////////
///////////////  secret_token

elasticApmAssertSame("getenv('ELASTIC_APM_SECRET_TOKEN')", getenv('ELASTIC_APM_SECRET_TOKEN'), '\|<>|/');

elasticApmAssertSame("elasticapm_get_config_option_by_name('secret_token')", elasticapm_get_config_option_by_name('secret_token'), '\|<>|/');

//////////////////////////////////////////////
///////////////  server_url

elasticApmAssertSame("getenv('ELASTIC_APM_SERVER_URL')", getenv('ELASTIC_APM_SERVER_URL'), '<\/\/>');

elasticApmAssertSame("elasticapm_get_config_option_by_name('server_url')", elasticapm_get_config_option_by_name('server_url'), '<\/\/>');

//////////////////////////////////////////////
///////////////  service_name

elasticApmAssertSame("getenv('ELASTIC_APM_SERVICE_NAME')", getenv('ELASTIC_APM_SERVICE_NAME'), '/\><\/');

elasticApmAssertSame("elasticapm_get_config_option_by_name('service_name')", elasticapm_get_config_option_by_name('service_name'), '/\><\/');

echo 'Test completed'
?>
--EXPECT--
Test completed
