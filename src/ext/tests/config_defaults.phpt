--TEST--
Verify configuration option's defaults
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=OFF
ELASTIC_APM_ENABLED=
ELASTIC_APM_LOG_FILE=
ELASTIC_APM_LOG_LEVEL=
ELASTIC_APM_LOG_LEVEL_FILE=
ELASTIC_APM_LOG_LEVEL_SYSLOG=
ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG=
ELASTIC_APM_SECRET_TOKEN=
ELASTIC_APM_SERVER_URL=
ELASTIC_APM_SERVICE_NAME=
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

//////////////////////////////////////////////
///////////////  enabled

elasticApmAssertSame("getenv('ELASTIC_APM_ENABLED')", getenv('ELASTIC_APM_ENABLED'), false);

elasticApmAssertEqual("ini_get('elasticapm.enabled')", ini_get('elasticapm.enabled'), false);

elasticApmAssertSame("elasticapm_is_enabled()", elasticapm_is_enabled(), true);

elasticApmAssertSame("elasticapm_get_config_option_by_name('enabled')", elasticapm_get_config_option_by_name('enabled'), true);

//////////////////////////////////////////////
///////////////  log_file

elasticApmAssertSame("getenv('ELASTIC_APM_LOG_FILE')", getenv('ELASTIC_APM_LOG_FILE'), false);

elasticApmAssertEqual("ini_get('elasticapm.log_file')", ini_get('elasticapm.log_file'), false);

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_file')", elasticapm_get_config_option_by_name('log_file'), null);

//////////////////////////////////////////////
///////////////  log_level

elasticApmAssertSame("getenv('ELASTIC_APM_LOG_LEVEL')", getenv('ELASTIC_APM_LOG_LEVEL'), false);

elasticApmAssertEqual("ini_get('elasticapm.log_level')", ini_get('elasticapm.log_level'), false);

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level')", elasticapm_get_config_option_by_name('log_level'), ELASTICAPM_LOG_LEVEL_NOT_SET);

//////////////////////////////////////////////
///////////////  log_level_file

elasticApmAssertSame("getenv('ELASTIC_APM_LOG_LEVEL_FILE')", getenv('ELASTIC_APM_LOG_LEVEL_FILE'), false);

elasticApmAssertEqual("ini_get('elasticapm.log_level_file')", ini_get('elasticapm.log_level_file'), false);

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_file')", elasticapm_get_config_option_by_name('log_level_file'), ELASTICAPM_LOG_LEVEL_NOT_SET);

//////////////////////////////////////////////
///////////////  log_level_syslog

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame("getenv('ELASTIC_APM_LOG_LEVEL_SYSLOG')", getenv('ELASTIC_APM_LOG_LEVEL_SYSLOG'), false);

    elasticApmAssertEqual("ini_get('elasticapm.log_level_syslog')", ini_get('elasticapm.log_level_syslog'), false);

    elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_syslog')", elasticapm_get_config_option_by_name('log_level_syslog'), ELASTICAPM_LOG_LEVEL_NOT_SET);
}

//////////////////////////////////////////////
///////////////  log_level_win_sys_debug

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame("getenv('ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG')", getenv('ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG'), false);

    elasticApmAssertEqual("ini_get('elasticapm.log_level_win_sys_debug')", ini_get('elasticapm.log_level_win_sys_debug'), false);

    elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_win_sys_debug')", elasticapm_get_config_option_by_name('log_level_win_sys_debug'), ELASTICAPM_LOG_LEVEL_NOT_SET);
}

//////////////////////////////////////////////
///////////////  secret_token

elasticApmAssertSame("getenv('ELASTIC_APM_SECRET_TOKEN')", getenv('ELASTIC_APM_SECRET_TOKEN'), false);

elasticApmAssertEqual("ini_get('elasticapm.secret_token')", ini_get('elasticapm.secret_token'), false);

elasticApmAssertSame("elasticapm_get_config_option_by_name('secret_token')", elasticapm_get_config_option_by_name('secret_token'), null);

//////////////////////////////////////////////
///////////////  server_url

elasticApmAssertSame("getenv('ELASTIC_APM_SERVER_URL')", getenv('ELASTIC_APM_SERVER_URL'), false);

elasticApmAssertEqual("ini_get('elasticapm.server_url')", ini_get('elasticapm.server_url'), false);

elasticApmAssertSame("elasticapm_get_config_option_by_name('server_url')", elasticapm_get_config_option_by_name('server_url'), 'http://localhost:8200');

//////////////////////////////////////////////
///////////////  service_name

elasticApmAssertSame("getenv('ELASTIC_APM_SERVICE_NAME')", getenv('ELASTIC_APM_SERVICE_NAME'), false);

elasticApmAssertEqual("ini_get('elasticapm.service_name')", ini_get('elasticapm.service_name'), false);

elasticApmAssertSame("elasticapm_get_config_option_by_name('service_name')", elasticapm_get_config_option_by_name('service_name'), 'Unknown PHP service');

echo 'Test completed'
?>
--EXPECT--
Test completed
