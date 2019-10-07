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
echo 'The extension "elasticapm" is available';
?>
--EXPECT--
The extension "elasticapm" is available
