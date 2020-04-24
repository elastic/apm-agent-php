--TEST--
Configuration in ini file has higher precedence than environment variables
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=OFF
ELASTIC_APM_LOG_FILE=log_file_from_env_vars.txt
ELASTIC_APM_LOG_LEVEL_FILE=off
--INI--
elasticapm.log_file=log_file_from_ini.txt
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertSame("getenv('ELASTIC_APM_LOG_FILE')", getenv('ELASTIC_APM_LOG_FILE'), 'log_file_from_env_vars.txt');

elasticApmAssertSame("ini_get('elasticapm.log_file')", ini_get('elasticapm.log_file'), 'log_file_from_ini.txt');

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_file')", elasticapm_get_config_option_by_name('log_file'), 'log_file_from_ini.txt');

echo 'Test completed'
?>
--EXPECT--
Test completed
