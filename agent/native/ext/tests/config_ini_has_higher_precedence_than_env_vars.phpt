--TEST--
Configuration in ini file has higher precedence than environment variables
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_APM_LOG_FILE=log_file_from_env_vars.txt
ELASTIC_APM_LOG_LEVEL_FILE=off
ELASTIC_APM_MAX_SEND_QUEUE_SIZE=123MB
--INI--
elastic_apm.log_file=log_file_from_ini.txt
elastic_apm.max_send_queue_size=456MB
elastic_apm.bootstrap_php_part_file=../../php/bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertSame("getenv('ELASTIC_APM_LOG_FILE')", getenv('ELASTIC_APM_LOG_FILE'), 'log_file_from_env_vars.txt');

elasticApmAssertSame("ini_get('elastic_apm.log_file')", ini_get('elastic_apm.log_file'), 'log_file_from_ini.txt');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_file')", elastic_apm_get_config_option_by_name('log_file'), 'log_file_from_ini.txt');

elasticApmAssertSame("getenv('ELASTIC_APM_MAX_SEND_QUEUE_SIZE')", getenv('ELASTIC_APM_MAX_SEND_QUEUE_SIZE'), '123MB');

elasticApmAssertSame("ini_get('elastic_apm.max_send_queue_size')", ini_get('elastic_apm.max_send_queue_size'), '456MB');

elasticApmAssertSame("elastic_apm_get_config_option_by_name('max_send_queue_size')", elastic_apm_get_config_option_by_name('max_send_queue_size'), 456.0 * 1024 * 1024);

echo 'Test completed'
?>
--EXPECT--
Test completed
