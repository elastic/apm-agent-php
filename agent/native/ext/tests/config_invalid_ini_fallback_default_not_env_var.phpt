--TEST--
When value in ini is invalid the fallback is the default and not environment variable
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_APM_ASSERT_LEVEL=O_n
ELASTIC_APM_MEMORY_TRACKING_LEVEL=ALL
ELASTIC_APM_VERIFY_SERVER_CERT=false
--INI--
elastic_apm.memory_tracking_level=not a valid memory tracking level
elastic_apm.verify_server_cert=not a valid bool
elastic_apm.bootstrap_php_part_file=../bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

// assert_level is not set in ini so it falls back on env vars
elasticApmAssertSame("elastic_apm_get_config_option_by_name('assert_level')", elastic_apm_get_config_option_by_name('assert_level'), ELASTIC_APM_ASSERT_LEVEL_O_N);
elasticApmAssertSame("getenv('ELASTIC_APM_ASSERT_LEVEL')", getenv('ELASTIC_APM_ASSERT_LEVEL'), 'O_n');

// memory_tracking_level is set in ini but the value is invalid so it falls back on default (which is `ELASTIC_APM_MEMORY_TRACKING_LEVEL_NOT_SET) and not the value set by env vars (which is ELASTIC_APM_MEMORY_TRACKING_LEVEL_ALL)
elasticApmAssertSame("elastic_apm_get_config_option_by_name('memory_tracking_level')", elastic_apm_get_config_option_by_name('memory_tracking_level'), ELASTIC_APM_MEMORY_TRACKING_LEVEL_NOT_SET);
elasticApmAssertSame("getenv('ELASTIC_APM_MEMORY_TRACKING_LEVEL')", getenv('ELASTIC_APM_MEMORY_TRACKING_LEVEL'), 'ALL');

// verify_server_cert is set in ini but the value is invalid so it falls back on default (which is `true`) and not the value set by env vars (which is `false`)
elasticApmAssertSame("elastic_apm_get_config_option_by_name('verify_server_cert')", elastic_apm_get_config_option_by_name('verify_server_cert'), true);
elasticApmAssertSame("ini_get('elastic_apm.verify_server_cert')", ini_get('elastic_apm.verify_server_cert'), 'not a valid bool');

echo 'Test completed'
?>
--EXPECT--
Test completed
