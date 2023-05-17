--TEST--
Setting configuration options to non-default value (in this case using ini file)
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
--INI--
elastic_apm.enabled=0
elastic_apm.log_file=non-default_log_file_value.txt
elastic_apm.log_level=CRITICAL
elastic_apm.log_level_file=TRACE
elastic_apm.log_level_syslog=TRACE
elastic_apm.log_level_win_sys_debug=CRITICAL
elastic_apm.secret_token=non-default_secret_token_123
elastic_apm.server_url=https://non-default_server_url:4321/some/path
elastic_apm.service_name=Non-default Service Name
elastic_apm.bootstrap_php_part_file=../bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

//////////////////////////////////////////////
///////////////  enabled

elasticApmAssertEqual("ini_get('elastic_apm.enabled')", ini_get('elastic_apm.enabled'), false);

elasticApmAssertSame("elastic_apm_get_config_option_by_name('enabled')", elastic_apm_get_config_option_by_name('enabled'), false);

elasticApmAssertSame("elastic_apm_is_enabled()", elastic_apm_is_enabled(), false);

//////////////////////////////////////////////
///////////////  log_file

elasticApmAssertSame("ini_get('elastic_apm.log_file')", ini_get('elastic_apm.log_file'), 'non-default_log_file_value.txt');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_file')", elastic_apm_get_config_option_by_name('log_file'), 'non-default_log_file_value.txt');

//////////////////////////////////////////////
///////////////  log_level

elasticApmAssertSame("ini_get('elastic_apm.log_level')", ini_get('elastic_apm.log_level'), 'CRITICAL');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level')", elastic_apm_get_config_option_by_name('log_level'), ELASTIC_APM_LOG_LEVEL_CRITICAL);

//////////////////////////////////////////////
///////////////  log_level_file

elasticApmAssertSame("ini_get('elastic_apm.log_level_file')", ini_get('elastic_apm.log_level_file'), 'TRACE');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_file')", elastic_apm_get_config_option_by_name('log_level_file'), ELASTIC_APM_LOG_LEVEL_TRACE);

//////////////////////////////////////////////
///////////////  log_level_syslog

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame("ini_get('elastic_apm.log_level_syslog')", ini_get('elastic_apm.log_level_syslog'), 'TRACE');

    elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_syslog')", elastic_apm_get_config_option_by_name('log_level_syslog'), ELASTIC_APM_LOG_LEVEL_TRACE);
}

//////////////////////////////////////////////
///////////////  log_level_win_sys_debug

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame("ini_get('elastic_apm.log_level_win_sys_debug')", ini_get('elastic_apm.log_level_win_sys_debug'), 'CRITICAL');
    elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_win_sys_debug')", elastic_apm_get_config_option_by_name('log_level_win_sys_debug'), ELASTIC_APM_LOG_LEVEL_CRITICAL);
}

//////////////////////////////////////////////
///////////////  secret_token

elasticApmAssertSame("ini_get('elastic_apm.secret_token')", ini_get('elastic_apm.secret_token'), 'non-default_secret_token_123');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('secret_token')", elastic_apm_get_config_option_by_name('secret_token'), 'non-default_secret_token_123');

//////////////////////////////////////////////
///////////////  server_url

elasticApmAssertSame("ini_get('elastic_apm.server_url')", ini_get('elastic_apm.server_url'), 'https://non-default_server_url:4321/some/path');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('server_url')", elastic_apm_get_config_option_by_name('server_url'), 'https://non-default_server_url:4321/some/path');

//////////////////////////////////////////////
///////////////  service_name

elasticApmAssertSame("ini_get('elastic_apm.service_name')", ini_get('elastic_apm.service_name'), 'Non-default Service Name');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('service_name')", elastic_apm_get_config_option_by_name('service_name'), 'Non-default Service Name');

echo 'Test completed'
?>
--EXPECT--
Test completed
