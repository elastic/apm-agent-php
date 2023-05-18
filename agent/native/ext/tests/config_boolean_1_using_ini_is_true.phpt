--TEST--
Boolean configuration option value 1 (in this case using ini file) should be interpreted as true
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
--INI--
elastic_apm.enabled=1
elastic_apm.bootstrap_php_part_file=../bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertSame("ini_get('elastic_apm.enabled')", ini_get('elastic_apm.enabled'), '1');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('enabled')", elastic_apm_get_config_option_by_name('enabled'), true);

echo 'Test completed'
?>
--EXPECT--
Test completed
