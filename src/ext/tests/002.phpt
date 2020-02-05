--TEST--
elasticapm should work even if service_name configuration option is not set
--SKIPIF--
<?php
if (!extension_loaded('elasticapm')) {
	echo 'skip';
}
?>
--FILE--
<?php
?>
--EXPECT--
