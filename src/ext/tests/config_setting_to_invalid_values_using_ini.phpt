--TEST--
Setting configuration option to invalid value via ini file
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
--INI--
elastic_apm.enabled=not valid boolean value
elastic_apm.assert_level=|:/:\:|
elastic_apm.secret_token=\|<>|/
elastic_apm.server_url=<\/\/>
elastic_apm.service_name=/\><\/
elastic_apm.bootstrap_php_part_file=../bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

//////////////////////////////////////////////
///////////////  enabled

elasticApmAssertEqual("ini_get('elastic_apm.enabled')", ini_get('elastic_apm.enabled'), 'not valid boolean value');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('enabled')", elastic_apm_get_config_option_by_name('enabled'), true);

elasticApmAssertSame("elastic_apm_is_enabled()", elastic_apm_is_enabled(), true);

//////////////////////////////////////////////
///////////////  assert_level

elasticApmAssertSame("ini_get('elastic_apm.assert_level')", ini_get('elastic_apm.assert_level'), '|:/:\:|');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('assert_level')", elastic_apm_get_config_option_by_name('assert_level'), ELASTIC_APM_ASSERT_LEVEL_NOT_SET);

//////////////////////////////////////////////
///////////////  secret_token

elasticApmAssertSame("ini_get('elastic_apm.secret_token')", ini_get('elastic_apm.secret_token'), '\|<>|/');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('secret_token')", elastic_apm_get_config_option_by_name('secret_token'), '\|<>|/');

//////////////////////////////////////////////
///////////////  server_url

elasticApmAssertSame("ini_get('elastic_apm.server_url')", ini_get('elastic_apm.server_url'), '<\/\/>');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('server_url')", elastic_apm_get_config_option_by_name('server_url'), '<\/\/>');

//////////////////////////////////////////////
///////////////  service_name

elasticApmAssertSame("ini_get('elastic_apm.service_name')", ini_get('elastic_apm.service_name'), '/\><\/');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('service_name')", elastic_apm_get_config_option_by_name('service_name'), '/\><\/');

echo 'Test completed'
?>
--EXPECT--
Test completed
