--TEST--
Boolean configuration option value 0 (in this case using ini file) should be interpreted as false
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=OFF
--INI--
elasticapm.enabled=0
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertSame("ini_get('elasticapm.enabled')", ini_get('elasticapm.enabled'), '0');

elasticApmAssertSame("elasticapm_is_enabled()", elasticapm_is_enabled(), false);

echo 'Test completed'
?>
--EXPECT--
Test completed
