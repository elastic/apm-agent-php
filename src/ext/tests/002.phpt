--TEST--
Check if elasticapm throws exception if service_name not specified
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
