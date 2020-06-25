--TEST--
Boolean configuration option value 1 (in this case using environment variable) should be interpreted as true
--SKIPIF--
<?php if ( ! extension_loaded( 'elastic_apm' ) ) die( 'skip'.'Extension elastic_apm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=OFF
ELASTIC_APM_ENABLED=1
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertSame("getenv('ELASTIC_APM_ENABLED')", getenv('ELASTIC_APM_ENABLED'), '1');

elasticApmAssertSame('elastic_apm_is_enabled()', elastic_apm_is_enabled(), true);

echo 'Test completed'
?>
--EXPECT--
Test completed
