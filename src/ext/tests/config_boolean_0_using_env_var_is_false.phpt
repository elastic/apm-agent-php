--TEST--
Boolean configuration option value 0 (in this case using environment variable) should be interpreted as false
--ENV--
ELASTIC_APM_ENABLED=0
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
--INI--
elastic_apm.bootstrap_php_part_file=../bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertSame("getenv('ELASTIC_APM_ENABLED')", getenv('ELASTIC_APM_ENABLED'), '0');

elasticApmAssertSame('elastic_apm_is_enabled()', elastic_apm_is_enabled(), false);

echo 'Test completed'
?>
--EXPECT--
Test completed
