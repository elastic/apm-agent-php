--TEST--
Check that elasticapm.enabled cannot be set with ini_set()
--SKIPIF--
<?php
if (!extension_loaded('elasticapm')) {
	echo 'skip';
}
?>
--FILE--
<?php
if (false === ini_set('elasticapm.enabled', 0)) {
    echo 'fail';
}
?>
--EXPECT--
fail