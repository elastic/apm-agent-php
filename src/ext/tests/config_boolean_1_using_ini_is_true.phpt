--TEST--
Boolean configuration option value 1 (in this case using ini file) should be interpreted as true
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=OFF
--INI--
elasticapm.enabled=1
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertSame(
    '1',
    ini_get('elasticapm.enabled'),
    "ini_get('elasticapm.enabled')"
);

elasticApmAssertSame(
    true,
    elasticapm_get_config_option_by_name('enabled'),
    "elasticapm_get_config_option_by_name('enabled')"
);

echo 'Test completed'
?>
--EXPECT--
Test completed
