--TEST--
When value in ini is invalid the fallback is the default and not environment variable
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_APM_LOG_LEVEL_FILE=CRITICAL
ELASTIC_APM_ASSERT_LEVEL=O_n
--INI--
elasticapm.log_level_file=not a valid log level
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

elasticApmAssertSame(
    'CRITICAL',
    getenv('ELASTIC_APM_LOG_LEVEL_FILE'),
    "getenv('ELASTIC_APM_LOG_LEVEL_FILE')"
);

elasticApmAssertSame(
    'O_n',
    getenv('ELASTIC_APM_ASSERT_LEVEL'),
    "getenv('ELASTIC_APM_ASSERT_LEVEL')"
);

elasticApmAssertSame(
    'not a valid log level',
    ini_get('elasticapm.log_level_file'),
    "ini_get('elasticapm.log_level_file')"
);

// log_level_file is set in ini albeit the value is invalid so it does fall back on env vars
elasticApmAssertSame(
    ELASTICAPM_LOG_LEVEL_NOT_SET,
    elasticapm_get_config_option_by_name('log_level_file'),
    "elasticapm_get_config_option_by_name('log_level_file')"
);

// assert_level is not set in ini so it does fall back on env vars
elasticApmAssertSame(
    ELASTICAPM_ASSERT_LEVEL_O_N,
    elasticapm_get_config_option_by_name('assert_level'),
    "elasticapm_get_config_option_by_name('assert_level')"
);

echo 'Test completed'
?>
--EXPECT--
Test completed
