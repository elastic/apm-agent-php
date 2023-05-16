--TEST--
Setting configuration options to non-default value (in this case using environment variables)
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_APM_ENABLED=0
ELASTIC_APM_LOG_FILE=non-default_log_file_value.txt
ELASTIC_APM_LOG_LEVEL=CRITICAL
ELASTIC_APM_LOG_LEVEL_FILE=TRACE
ELASTIC_APM_LOG_LEVEL_SYSLOG=TRACE
ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG=CRITICAL
ELASTIC_APM_SECRET_TOKEN=non-default_secret_token_123
ELASTIC_APM_SERVER_URL=https://non-default_server_url:4321/some/path
ELASTIC_APM_SERVICE_NAME=Non-default Service Name
--INI--
elastic_apm.bootstrap_php_part_file=../bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

//////////////////////////////////////////////
///////////////  enabled

elasticApmAssertSame("getenv('ELASTIC_APM_ENABLED')", getenv('ELASTIC_APM_ENABLED'), '0');

elasticApmAssertSame("elastic_apm_is_enabled()", elastic_apm_is_enabled(), false);

elasticApmAssertSame("elastic_apm_get_config_option_by_name('enabled')", elastic_apm_get_config_option_by_name('enabled'), false);

//////////////////////////////////////////////
///////////////  log_file

elasticApmAssertSame("getenv('ELASTIC_APM_LOG_FILE')", getenv('ELASTIC_APM_LOG_FILE'), 'non-default_log_file_value.txt');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_file')", elastic_apm_get_config_option_by_name('log_file'), 'non-default_log_file_value.txt');

//////////////////////////////////////////////
///////////////  log_level

elasticApmAssertSame("getenv('ELASTIC_APM_LOG_LEVEL')", getenv('ELASTIC_APM_LOG_LEVEL'), 'CRITICAL');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level')", elastic_apm_get_config_option_by_name('log_level'), ELASTIC_APM_LOG_LEVEL_CRITICAL);

//////////////////////////////////////////////
///////////////  log_level_file

elasticApmAssertSame("getenv('ELASTIC_APM_LOG_LEVEL_FILE')", getenv('ELASTIC_APM_LOG_LEVEL_FILE'), 'TRACE');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_file')", elastic_apm_get_config_option_by_name('log_level_file'), ELASTIC_APM_LOG_LEVEL_TRACE);

//////////////////////////////////////////////
///////////////  log_level_syslog

elasticApmAssertSame("getenv('ELASTIC_APM_LOG_LEVEL_SYSLOG')", getenv('ELASTIC_APM_LOG_LEVEL_SYSLOG'), 'TRACE');

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_syslog')", elastic_apm_get_config_option_by_name('log_level_syslog'), ELASTIC_APM_LOG_LEVEL_TRACE);
}

//////////////////////////////////////////////
///////////////  log_level_win_sys_debug

elasticApmAssertSame("getenv('ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG')", getenv('ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG'), 'CRITICAL');

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_win_sys_debug')", elastic_apm_get_config_option_by_name('log_level_win_sys_debug'), ELASTIC_APM_LOG_LEVEL_CRITICAL);
}

//////////////////////////////////////////////
///////////////  secret_token

elasticApmAssertSame("getenv('ELASTIC_APM_SECRET_TOKEN')", getenv('ELASTIC_APM_SECRET_TOKEN'), 'non-default_secret_token_123');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('secret_token')", elastic_apm_get_config_option_by_name('secret_token'), 'non-default_secret_token_123');

//////////////////////////////////////////////
///////////////  server_url

elasticApmAssertSame("getenv('ELASTIC_APM_SERVER_URL')", getenv('ELASTIC_APM_SERVER_URL'), 'https://non-default_server_url:4321/some/path');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('server_url')", elastic_apm_get_config_option_by_name('server_url'), 'https://non-default_server_url:4321/some/path');

//////////////////////////////////////////////
///////////////  service_name

elasticApmAssertSame("getenv('ELASTIC_APM_SERVICE_NAME')", getenv('ELASTIC_APM_SERVICE_NAME'), 'Non-default Service Name');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('service_name')", elastic_apm_get_config_option_by_name('service_name'), 'Non-default Service Name');

echo 'Test completed'
?>
--EXPECT--
Test completed
