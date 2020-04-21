--TEST--
Boolean configuration option value 'TRue' (in this case using ini file) should be interpreted as true and it should be case insensitive
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=OFF
--INI--
elasticapm.enabled=TRue
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util//bootstrap.php';

elasticApmAssertEqual(
    true,
    ini_get('elasticapm.enabled'),
    "ini_get('elasticapm.enabled')"
);

elasticApmAssertSame(
    true,
    elasticapm_is_enabled(),
    "elasticapm_is_enabled()"
);

echo 'Test completed'
?>
--EXPECT--
Test completed
