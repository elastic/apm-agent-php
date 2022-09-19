--TEST--
Setting configuration options to non-default value (in this case using ini file)
--SKIPIF--
<?php if ( ! extension_loaded( 'elastic_apm' ) ) die( 'skip'.'Extension elastic_apm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
--INI--
elastic_apm.enabled=not_valid_boolean_value
elastic_apm.log_file=
elastic_apm.log_level=not valid log level
elastic_apm.log_level_file=not valid log level
elastic_apm.log_level_syslog=not valid log level
elastic_apm.log_level_win_sys_debug=not valid log level
elastic_apm.secret_token=
elastic_apm.server_url=
elastic_apm.service_name=
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

//////////////////////////////////////////////
///////////////  enabled

elasticApmAssertEqual("ini_get('elastic_apm.enabled')", ini_get('elastic_apm.enabled'), 'not_valid_boolean_value');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('enabled')", elastic_apm_get_config_option_by_name('enabled'), true);

elasticApmAssertSame("elastic_apm_is_enabled()", elastic_apm_is_enabled(), true);

//////////////////////////////////////////////
///////////////  log_file

elasticApmAssertSame("ini_get('elastic_apm.log_file')", ini_get('elastic_apm.log_file'), '');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_file')", elastic_apm_get_config_option_by_name('log_file'), '');

//////////////////////////////////////////////
///////////////  log_level

elasticApmAssertSame("ini_get('elastic_apm.log_level')", ini_get('elastic_apm.log_level'), 'not valid log level');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level')", elastic_apm_get_config_option_by_name('log_level'), ELASTIC_APM_LOG_LEVEL_NOT_SET);

//////////////////////////////////////////////
///////////////  log_level_file

elasticApmAssertSame("ini_get('elastic_apm.log_level_file')", ini_get('elastic_apm.log_level_file'), 'not valid log level');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_file')", elastic_apm_get_config_option_by_name('log_level_file'), ELASTIC_APM_LOG_LEVEL_NOT_SET);

//////////////////////////////////////////////
///////////////  log_level_syslog

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame("ini_get('elastic_apm.log_level_syslog')", ini_get('elastic_apm.log_level_syslog'), 'not valid log level');

    elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_syslog')", elastic_apm_get_config_option_by_name('log_level_syslog'), ELASTIC_APM_LOG_LEVEL_NOT_SET);
}

//////////////////////////////////////////////
///////////////  log_level_win_sys_debug

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame("ini_get('elastic_apm.log_level_win_sys_debug')", ini_get('elastic_apm.log_level_win_sys_debug'), 'not valid log level');
    elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_win_sys_debug')", elastic_apm_get_config_option_by_name('log_level_win_sys_debug'), ELASTIC_APM_LOG_LEVEL_NOT_SET);
}

//////////////////////////////////////////////
///////////////  secret_token

elasticApmAssertSame("ini_get('elastic_apm.secret_token')", ini_get('elastic_apm.secret_token'), '');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('secret_token')", elastic_apm_get_config_option_by_name('secret_token'), '');

//////////////////////////////////////////////
///////////////  server_url

elasticApmAssertSame("ini_get('elastic_apm.server_url')", ini_get('elastic_apm.server_url'), '');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('server_url')", elastic_apm_get_config_option_by_name('server_url'), '');

//////////////////////////////////////////////
///////////////  service_name

elasticApmAssertSame("ini_get('elastic_apm.service_name')", ini_get('elastic_apm.service_name'), '');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('service_name')", elastic_apm_get_config_option_by_name('service_name'), '');

echo 'Test completed'
?>
--EXPECT--
Test completed
