--TEST--
Verify configuration option's defaults
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_APM_ENABLED=
ELASTIC_APM_LOG_FILE=
ELASTIC_APM_LOG_LEVEL=
ELASTIC_APM_LOG_LEVEL_FILE=
ELASTIC_APM_LOG_LEVEL_SYSLOG=
ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG=
ELASTIC_APM_SECRET_TOKEN=
ELASTIC_APM_SERVER_URL=
ELASTIC_APM_SERVICE_NAME=
--INI--
elastic_apm.bootstrap_php_part_file=../bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

//////////////////////////////////////////////
///////////////  enabled

elasticApmAssertSame("getenv('ELASTIC_APM_ENABLED')", getenv('ELASTIC_APM_ENABLED'), false);

elasticApmAssertEqual("ini_get('elastic_apm.enabled')", ini_get('elastic_apm.enabled'), false);

elasticApmAssertSame("elastic_apm_is_enabled()", elastic_apm_is_enabled(), true);

elasticApmAssertSame("elastic_apm_get_config_option_by_name('enabled')", elastic_apm_get_config_option_by_name('enabled'), true);

//////////////////////////////////////////////
///////////////  log_file

elasticApmAssertSame("getenv('ELASTIC_APM_LOG_FILE')", getenv('ELASTIC_APM_LOG_FILE'), false);

elasticApmAssertEqual("ini_get('elastic_apm.log_file')", ini_get('elastic_apm.log_file'), false);

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_file')", elastic_apm_get_config_option_by_name('log_file'), null);

//////////////////////////////////////////////
///////////////  log_level

elasticApmAssertSame("getenv('ELASTIC_APM_LOG_LEVEL')", getenv('ELASTIC_APM_LOG_LEVEL'), false);

elasticApmAssertEqual("ini_get('elastic_apm.log_level')", ini_get('elastic_apm.log_level'), false);

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level')", elastic_apm_get_config_option_by_name('log_level'), ELASTIC_APM_LOG_LEVEL_NOT_SET);

//////////////////////////////////////////////
///////////////  log_level_file

elasticApmAssertSame("getenv('ELASTIC_APM_LOG_LEVEL_FILE')", getenv('ELASTIC_APM_LOG_LEVEL_FILE'), false);

elasticApmAssertEqual("ini_get('elastic_apm.log_level_file')", ini_get('elastic_apm.log_level_file'), false);

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_file')", elastic_apm_get_config_option_by_name('log_level_file'), ELASTIC_APM_LOG_LEVEL_NOT_SET);

//////////////////////////////////////////////
///////////////  log_level_syslog

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame("getenv('ELASTIC_APM_LOG_LEVEL_SYSLOG')", getenv('ELASTIC_APM_LOG_LEVEL_SYSLOG'), false);

    elasticApmAssertEqual("ini_get('elastic_apm.log_level_syslog')", ini_get('elastic_apm.log_level_syslog'), false);

    elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_syslog')", elastic_apm_get_config_option_by_name('log_level_syslog'), ELASTIC_APM_LOG_LEVEL_NOT_SET);
}

//////////////////////////////////////////////
///////////////  log_level_win_sys_debug

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame("getenv('ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG')", getenv('ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG'), false);

    elasticApmAssertEqual("ini_get('elastic_apm.log_level_win_sys_debug')", ini_get('elastic_apm.log_level_win_sys_debug'), false);

    elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_win_sys_debug')", elastic_apm_get_config_option_by_name('log_level_win_sys_debug'), ELASTIC_APM_LOG_LEVEL_NOT_SET);
}

//////////////////////////////////////////////
///////////////  secret_token

elasticApmAssertSame("getenv('ELASTIC_APM_SECRET_TOKEN')", getenv('ELASTIC_APM_SECRET_TOKEN'), false);

elasticApmAssertEqual("ini_get('elastic_apm.secret_token')", ini_get('elastic_apm.secret_token'), false);

elasticApmAssertSame("elastic_apm_get_config_option_by_name('secret_token')", elastic_apm_get_config_option_by_name('secret_token'), null);

//////////////////////////////////////////////
///////////////  server_url

elasticApmAssertSame("getenv('ELASTIC_APM_SERVER_URL')", getenv('ELASTIC_APM_SERVER_URL'), false);

elasticApmAssertEqual("ini_get('elastic_apm.server_url')", ini_get('elastic_apm.server_url'), false);

elasticApmAssertSame("elastic_apm_get_config_option_by_name('server_url')", elastic_apm_get_config_option_by_name('server_url'), 'http://localhost:8200');

//////////////////////////////////////////////
///////////////  service_name

elasticApmAssertSame("getenv('ELASTIC_APM_SERVICE_NAME')", getenv('ELASTIC_APM_SERVICE_NAME'), false);

elasticApmAssertEqual("ini_get('elastic_apm.service_name')", ini_get('elastic_apm.service_name'), false);

elasticApmAssertSame("elastic_apm_get_config_option_by_name('service_name')", elastic_apm_get_config_option_by_name('service_name'), null);

echo 'Test completed'
?>
--EXPECT--
Test completed
