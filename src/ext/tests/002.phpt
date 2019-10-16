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
ini_set('elasticapm.enable', 1);
?>
--EXPECT--
Fatal error: Uncaught RuntimeException: You need to specify a service name in elasticapm.service_name in [no active file]:0
Stack trace:
#0 {main}
  thrown in [no active file] on line 0
