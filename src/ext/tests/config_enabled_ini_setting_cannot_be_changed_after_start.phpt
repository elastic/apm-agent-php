--TEST--
Check that elastic_apm.enabled cannot be set with ini_set()
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
--INI--
elastic_apm.bootstrap_php_part_file=../bootstrap_php_part.php
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
