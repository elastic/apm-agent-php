--TEST--
Check if elasticapm is loaded
--SKIPIF--
<?php
if (!extension_loaded('elasticapm')) {
	echo 'skip';
}
?>
--FILE--
<?php
if (!extension_loaded('elasticapm')) {
	echo 'extension_loaded( \'elasticapm\' ): ' . ( extension_loaded('elasticapm') ? 'true' : 'false' );
}
?>
--EXPECT--

