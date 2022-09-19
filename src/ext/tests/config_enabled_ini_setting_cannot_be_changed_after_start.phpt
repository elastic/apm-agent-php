--TEST--
Check that elastic_apm.enabled cannot be set with ini_set()
--SKIPIF--
<?php
if (!extension_loaded('elastic_apm')) {
	echo 'skip';
}
?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

if (ini_set('elastic_apm.enabled', 'new value') === false) {
    echo 'ini_set returned false';
}
?>
--EXPECT--
ini_set returned false
