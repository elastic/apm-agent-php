--TEST--
Check that elastic_apm.enabled cannot be set with ini_set()
--SKIPIF--
<?php
if (!extension_loaded('elastic_apm')) {
	echo 'skip';
}
?>
--FILE--
<?php
if (false === ini_set('elastic_apm.enabled', 0)) {
    echo 'fail';
}
?>
--EXPECT--
fail