--TEST--
AutoloadedFromExtension class in the file pointed by elasticapm.autoload_file should be auto-loaded and AutoloadedFromExtension::callFromExtension should be called by extension
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--INI--
elasticapm.autoload_file=../autoload.php
--FILE--
<?php
if (!\ElasticApm\AutoloadedFromExtension::wasCalledByExtension()) {
    die('\AutoloadedFromExtension::wasCalledByExtension(): false');
}

echo 'Test completed'
?>
--EXPECT--
Test completed
