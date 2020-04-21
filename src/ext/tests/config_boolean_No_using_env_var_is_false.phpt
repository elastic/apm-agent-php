--TEST--
Boolean configuration option value 'no' (in this case using environment variable) should be interpreted as false and it should be case insensitive
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=OFF
ELASTIC_APM_ENABLED=No
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

elasticApmAssertSame(
    'No',
    getenv('ELASTIC_APM_ENABLED'),
    "getenv('ELASTIC_APM_ENABLED')"
);

elasticApmAssertSame(
    false,
    elasticapm_is_enabled(),
    "elasticapm_is_enabled()"
);

echo 'Test completed'
?>
--EXPECT--
Test completed
