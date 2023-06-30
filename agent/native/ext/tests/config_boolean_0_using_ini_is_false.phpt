--TEST--
Boolean configuration option value 0 (in this case using ini file) should be interpreted as false
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
--INI--
elastic_apm.enabled=0
elastic_apm.bootstrap_php_part_file=../../php/bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertSame("ini_get('elastic_apm.enabled')", ini_get('elastic_apm.enabled'), '0');

elasticApmAssertSame("elastic_apm_is_enabled()", elastic_apm_is_enabled(), false);

echo 'Test completed'
?>
--EXPECT--
Test completed
