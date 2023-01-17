--TEST--
Boolean configuration option value 1 (in this case using environment variable) should be interpreted as true
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_APM_ENABLED=1
--INI--
elastic_apm.bootstrap_php_part_file=../bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertSame("getenv('ELASTIC_APM_ENABLED')", getenv('ELASTIC_APM_ENABLED'), '1');

elasticApmAssertSame('elastic_apm_is_enabled()', elastic_apm_is_enabled(), true);

echo 'Test completed'
?>
--EXPECT--
Test completed
