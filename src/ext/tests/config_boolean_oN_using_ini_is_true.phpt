--TEST--
Boolean configuration option value 'oN' (in this case using ini file) should be interpreted as true and it should be case insensitive
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
--INI--
elastic_apm.enabled=oN
elastic_apm.bootstrap_php_part_file=../bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertEqual("ini_get('elastic_apm.enabled')", ini_get('elastic_apm.enabled'), true);

elasticApmAssertSame("elastic_apm_is_enabled()", elastic_apm_is_enabled(), true);

echo 'Test completed'
?>
--EXPECT--
Test completed
