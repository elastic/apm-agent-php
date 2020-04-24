--TEST--
Setting configuration options to non-default value (in this case using ini file)
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=OFF
--INI--
elasticapm.enabled=0
elasticapm.log_file=non-default_log_file_value.txt
elasticapm.log_level=CRITICAL
elasticapm.log_level_file=TRACE
elasticapm.log_level_syslog=TRACE
elasticapm.log_level_win_sys_debug=CRITICAL
elasticapm.secret_token=non-default_secret_token_123
elasticapm.server_url=https://non-default_server_url:4321/some/path
elasticapm.service_name=Non-default Service Name
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

//////////////////////////////////////////////
///////////////  enabled

elasticApmAssertEqual("ini_get('elasticapm.enabled')", ini_get('elasticapm.enabled'), false);

elasticApmAssertSame("elasticapm_get_config_option_by_name('enabled')", elasticapm_get_config_option_by_name('enabled'), false);

elasticApmAssertSame("elasticapm_is_enabled()", elasticapm_is_enabled(), false);

//////////////////////////////////////////////
///////////////  log_file

elasticApmAssertSame("ini_get('elasticapm.log_file')", ini_get('elasticapm.log_file'), 'non-default_log_file_value.txt');

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_file')", elasticapm_get_config_option_by_name('log_file'), 'non-default_log_file_value.txt');

//////////////////////////////////////////////
///////////////  log_level

elasticApmAssertSame("ini_get('elasticapm.log_level')", ini_get('elasticapm.log_level'), 'CRITICAL');

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level')", elasticapm_get_config_option_by_name('log_level'), ELASTICAPM_LOG_LEVEL_CRITICAL);

//////////////////////////////////////////////
///////////////  log_level_file

elasticApmAssertSame("ini_get('elasticapm.log_level_file')", ini_get('elasticapm.log_level_file'), 'TRACE');

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_file')", elasticapm_get_config_option_by_name('log_level_file'), ELASTICAPM_LOG_LEVEL_TRACE);

//////////////////////////////////////////////
///////////////  log_level_syslog

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame("ini_get('elasticapm.log_level_syslog')", ini_get('elasticapm.log_level_syslog'), 'TRACE');

    elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_syslog')", elasticapm_get_config_option_by_name('log_level_syslog'), ELASTICAPM_LOG_LEVEL_TRACE);
}

//////////////////////////////////////////////
///////////////  log_level_win_sys_debug

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame("ini_get('elasticapm.log_level_win_sys_debug')", ini_get('elasticapm.log_level_win_sys_debug'), 'CRITICAL');
    elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_win_sys_debug')", elasticapm_get_config_option_by_name('log_level_win_sys_debug'), ELASTICAPM_LOG_LEVEL_CRITICAL);
}

//////////////////////////////////////////////
///////////////  secret_token

elasticApmAssertSame("ini_get('elasticapm.secret_token')", ini_get('elasticapm.secret_token'), 'non-default_secret_token_123');

elasticApmAssertSame("elasticapm_get_config_option_by_name('secret_token')", elasticapm_get_config_option_by_name('secret_token'), 'non-default_secret_token_123');

//////////////////////////////////////////////
///////////////  server_url

elasticApmAssertSame("ini_get('elasticapm.server_url')", ini_get('elasticapm.server_url'), 'https://non-default_server_url:4321/some/path');

elasticApmAssertSame("elasticapm_get_config_option_by_name('server_url')", elasticapm_get_config_option_by_name('server_url'), 'https://non-default_server_url:4321/some/path');

//////////////////////////////////////////////
///////////////  service_name

elasticApmAssertSame("ini_get('elasticapm.service_name')", ini_get('elasticapm.service_name'), 'Non-default Service Name');

elasticApmAssertSame("elasticapm_get_config_option_by_name('service_name')", elasticapm_get_config_option_by_name('service_name'), 'Non-default Service Name');

echo 'Test completed'
?>
--EXPECT--
Test completed
