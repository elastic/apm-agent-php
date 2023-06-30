--TEST--
Detection of opcache preload feature
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=DEBUG
ELASTIC_APM_ENABLED=true
--INI--
elastic_apm.enabled = 1
elastic_apm.bootstrap_php_part_file=../../php/bootstrap_php_part.php
zend_extension=/tmp/extensions/opcache.so
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/opcache_preload_detection.inc
opcache.preload_user=root
--SKIPIF--
<?php
if (PHP_VERSION_ID < 70400) die("skip ElasticApmSkipTest Unsupported PHP version");
?>
--FILE--
<?php
declare(strict_types=1);

echo 'Preloaded function exists: '.function_exists('preloadedFunction').PHP_EOL;
echo 'Test completed';
?>
--EXPECTF--
%aopcache.preload request detected on init%aopcache.preload request detected on shutdown%aPreloaded function exists: 1
Test completed%a