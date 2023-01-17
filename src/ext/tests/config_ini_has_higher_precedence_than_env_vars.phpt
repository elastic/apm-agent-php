--TEST--
Configuration in ini file has higher precedence than environment variables
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_APM_LOG_FILE=log_file_from_env_vars.txt
ELASTIC_APM_LOG_LEVEL_FILE=off
--INI--
elastic_apm.log_file=log_file_from_ini.txt
elastic_apm.bootstrap_php_part_file=../bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertSame("getenv('ELASTIC_APM_LOG_FILE')", getenv('ELASTIC_APM_LOG_FILE'), 'log_file_from_env_vars.txt');

elasticApmAssertSame("ini_get('elastic_apm.log_file')", ini_get('elastic_apm.log_file'), 'log_file_from_ini.txt');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_file')", elastic_apm_get_config_option_by_name('log_file'), 'log_file_from_ini.txt');

echo 'Test completed'
?>
--EXPECT--
Test completed
