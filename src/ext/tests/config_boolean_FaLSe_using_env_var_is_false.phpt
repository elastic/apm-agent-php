--TEST--
Boolean configuration option value 'FaLSe' (in this case using environment variable) should be interpreted as false and it should be case insensitive
--SKIPIF--
<?php if ( ! extension_loaded( 'elastic_apm' ) ) die( 'skip'.'Extension elastic_apm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_APM_ENABLED=FaLSe
--INI--
elastic_apm.bootstrap_php_part_file=../bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertSame("getenv('ELASTIC_APM_ENABLED')", getenv('ELASTIC_APM_ENABLED'), 'FaLSe');

elasticApmAssertSame("elastic_apm_is_enabled()", elastic_apm_is_enabled(), false);

echo 'Test completed'
?>
--EXPECT--
Test completed
