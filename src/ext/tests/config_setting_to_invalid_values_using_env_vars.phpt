--TEST--
Setting configuration option to invalid value via environment variables
--SKIPIF--
<?php if ( ! extension_loaded( 'elastic_apm' ) ) die( 'skip'.'Extension elastic_apm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_APM_ENABLED=not_valid_boolean_value
ELASTIC_APM_LOG_FILE=|:/:\:|
ELASTIC_APM_LOG_LEVEL=not valid log level
ELASTIC_APM_LOG_LEVEL_FILE=not valid log level
ELASTIC_APM_LOG_LEVEL_SYSLOG=not valid log level
ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG=not valid log level
ELASTIC_APM_SECRET_TOKEN=\|<>|/
ELASTIC_APM_SERVER_URL=<\/\/>
ELASTIC_APM_SERVICE_NAME=/\><\/
--INI--
elastic_apm.bootstrap_php_part_file=../bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

//////////////////////////////////////////////
///////////////  enabled

elasticApmAssertSame("getenv('ELASTIC_APM_ENABLED')", getenv('ELASTIC_APM_ENABLED'), 'not valid boolean value');

elasticApmAssertSame("elastic_apm_is_enabled()", elastic_apm_is_enabled(), true);

elasticApmAssertSame("elastic_apm_get_config_option_by_name('enabled')", elastic_apm_get_config_option_by_name('enabled'), true);

//////////////////////////////////////////////
///////////////  assert_level

elasticApmAssertSame("getenv('ELASTIC_APM_ASSERT_LEVEL')", getenv('ELASTIC_APM_ASSERT_LEVEL'), '|:/:\:|');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('assert_level')", elastic_apm_get_config_option_by_name('assert_level'), ELASTIC_APM_ASSERT_LEVEL_NOT_SET);

//////////////////////////////////////////////
///////////////  secret_token

elasticApmAssertSame("getenv('ELASTIC_APM_SECRET_TOKEN')", getenv('ELASTIC_APM_SECRET_TOKEN'), '\|<>|/');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('secret_token')", elastic_apm_get_config_option_by_name('secret_token'), '\|<>|/');

//////////////////////////////////////////////
///////////////  server_url

elasticApmAssertSame("getenv('ELASTIC_APM_SERVER_URL')", getenv('ELASTIC_APM_SERVER_URL'), '<\/\/>');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('server_url')", elastic_apm_get_config_option_by_name('server_url'), '<\/\/>');

//////////////////////////////////////////////
///////////////  service_name

elasticApmAssertSame("getenv('ELASTIC_APM_SERVICE_NAME')", getenv('ELASTIC_APM_SERVICE_NAME'), '/\><\/');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('service_name')", elastic_apm_get_config_option_by_name('service_name'), '/\><\/');

echo 'Test completed'
?>
--EXPECT--
Test completed
