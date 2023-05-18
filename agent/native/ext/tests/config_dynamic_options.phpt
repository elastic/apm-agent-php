--TEST--
Which configuration options are dynamic
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
--INI--
elastic_apm.bootstrap_php_part_file=../bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

$dynamicConfigOptNames = [ 'log_level' ];

elasticApmAssertSame('elastic_apm_get_number_of_dynamic_config_options()', elastic_apm_get_number_of_dynamic_config_options(), count($dynamicConfigOptNames));

echo 'Test completed'
?>
--EXPECT--
Test completed
