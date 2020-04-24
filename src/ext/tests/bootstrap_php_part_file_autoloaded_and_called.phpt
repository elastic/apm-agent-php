--TEST--
Extension should auto-load file pointed by `elasticapm.bootstrap_php_part_file' and call `\ElasticApm\Impl\bootstrapPhpPart()' function
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--INI--
elasticapm.bootstrap_php_part_file=../bootstrapPhpPart.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertSame('\ElasticApm\Impl\wasBootstrapPhpPartCalled()', \ElasticApm\Impl\wasBootstrapPhpPartCalled(), true);

echo 'Test completed'
?>
--EXPECT--
Test completed
