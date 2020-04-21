--TEST--
Boolean configuration option value 1 (in this case using environment variable) should be interpreted as true
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=OFF
ELASTIC_APM_ENABLED=1
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util//bootstrap.php';

elasticApmAssertSame(
    '1',
    getenv('ELASTIC_APM_ENABLED'),
    "getenv('ELASTIC_APM_ENABLED')"
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
