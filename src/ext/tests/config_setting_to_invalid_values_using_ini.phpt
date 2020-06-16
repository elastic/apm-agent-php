--TEST--
Setting configuration options to non-default value (in this case using ini file)
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=OFF
--INI--
elasticapm.enabled=not_valid_boolean_value
elasticapm.log_file=
elasticapm.log_level=not valid log level
elasticapm.log_level_file=not valid log level
elasticapm.log_level_syslog=not valid log level
elasticapm.log_level_win_sys_debug=not valid log level
elasticapm.secret_token=
elasticapm.server_url=
elasticapm.service_name=
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

//////////////////////////////////////////////
///////////////  enabled

elasticApmAssertEqual("ini_get('elasticapm.enabled')", ini_get('elasticapm.enabled'), 'not_valid_boolean_value');

elasticApmAssertSame("elasticapm_get_config_option_by_name('enabled')", elasticapm_get_config_option_by_name('enabled'), true);

elasticApmAssertSame("elasticapm_is_enabled()", elasticapm_is_enabled(), true);

//////////////////////////////////////////////
///////////////  log_file

elasticApmAssertSame("ini_get('elasticapm.log_file')", ini_get('elasticapm.log_file'), '');

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_file')", elasticapm_get_config_option_by_name('log_file'), '');

//////////////////////////////////////////////
///////////////  log_level

elasticApmAssertSame("ini_get('elasticapm.log_level')", ini_get('elasticapm.log_level'), 'not valid log level');

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level')", elasticapm_get_config_option_by_name('log_level'), ELASTICAPM_LOG_LEVEL_NOT_SET);

//////////////////////////////////////////////
///////////////  log_level_file

elasticApmAssertSame("ini_get('elasticapm.log_level_file')", ini_get('elasticapm.log_level_file'), 'not valid log level');

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_file')", elasticapm_get_config_option_by_name('log_level_file'), ELASTICAPM_LOG_LEVEL_NOT_SET);

//////////////////////////////////////////////
///////////////  log_level_syslog

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame("ini_get('elasticapm.log_level_syslog')", ini_get('elasticapm.log_level_syslog'), 'not valid log level');

    elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_syslog')", elasticapm_get_config_option_by_name('log_level_syslog'), ELASTICAPM_LOG_LEVEL_NOT_SET);
}

//////////////////////////////////////////////
///////////////  log_level_win_sys_debug

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame("ini_get('elasticapm.log_level_win_sys_debug')", ini_get('elasticapm.log_level_win_sys_debug'), 'not valid log level');
    elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_win_sys_debug')", elasticapm_get_config_option_by_name('log_level_win_sys_debug'), ELASTICAPM_LOG_LEVEL_NOT_SET);
}

//////////////////////////////////////////////
///////////////  secret_token

elasticApmAssertSame("ini_get('elasticapm.secret_token')", ini_get('elasticapm.secret_token'), '');

elasticApmAssertSame("elasticapm_get_config_option_by_name('secret_token')", elasticapm_get_config_option_by_name('secret_token'), '');

//////////////////////////////////////////////
///////////////  server_url

elasticApmAssertSame("ini_get('elasticapm.server_url')", ini_get('elasticapm.server_url'), '');

elasticApmAssertSame("elasticapm_get_config_option_by_name('server_url')", elasticapm_get_config_option_by_name('server_url'), '');

//////////////////////////////////////////////
///////////////  service_name

elasticApmAssertSame("ini_get('elasticapm.service_name')", ini_get('elasticapm.service_name'), '');

elasticApmAssertSame("elasticapm_get_config_option_by_name('service_name')", elasticapm_get_config_option_by_name('service_name'), '');

echo 'Test completed'
?>
--EXPECT--
Test completed
