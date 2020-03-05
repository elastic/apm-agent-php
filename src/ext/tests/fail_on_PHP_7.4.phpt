--TEST--
This test intentionally fails on PHP 7.4
--FILE--
<?php

if (PHP_MAJOR_VERSION == 7 && PHP_MINOR_VERSION == 4) {
    die('This test intentionally fails on specific PHP version');
}

echo 'Test completed'
?>
--EXPECT--
Test completed
