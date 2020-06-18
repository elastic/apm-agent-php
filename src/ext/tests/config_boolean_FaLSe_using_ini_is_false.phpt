--TEST--
Boolean configuration option value 'FaLSe' (in this case using ini file) should be interpreted as false and it should be case insensitive
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--INI--
ELASTIC_APM_LOG_LEVEL_STDERR=OFF
elasticapm.enabled=FaLSe
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertEqual("ini_get('elasticapm.enabled')", ini_get('elasticapm.enabled'), false);

elasticApmAssertSame("elasticapm_is_enabled()", elasticapm_is_enabled(), false);

echo 'Test completed'
?>
--EXPECT--
Test completed
