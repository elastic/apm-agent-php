--TEST--
Which configuration options are dynamic
--SKIPIF--
<?php if ( ! extension_loaded( 'elastic_apm' ) ) die( 'skip'.'Extension elastic_apm must be installed' ); ?>
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
