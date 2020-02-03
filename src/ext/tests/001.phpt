--TEST--
Check if elasticapm is loaded
--SKIPIF--
<?php
?>
--FILE--
<?php
echo 'extension_loaded( \'elasticapm\' ): ' . ( extension_loaded('elasticapm') ? 'true' : 'false' );
?>
--EXPECT--
extension_loaded( 'elasticapm' ): true
