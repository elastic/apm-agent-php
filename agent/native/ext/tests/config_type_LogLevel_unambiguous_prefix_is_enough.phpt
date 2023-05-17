--TEST--
Configuration values of type LogLevel: it is enough to provide unambiguous prefix
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_APM_LOG_LEVEL=warn
ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG=TRa
--INI--
elastic_apm.log_level_syslog=Er
elastic_apm.log_level_file=dEb
elastic_apm.bootstrap_php_part_file=../bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

if ( ! extension_loaded( 'elastic_apm' ) ) die( 'Extension elastic_apm must be installed' );

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level')", elastic_apm_get_config_option_by_name('log_level'), ELASTIC_APM_LOG_LEVEL_WARNING);

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_syslog')", elastic_apm_get_config_option_by_name('log_level_syslog'), ELASTIC_APM_LOG_LEVEL_ERROR);
}

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_win_sys_debug')", elastic_apm_get_config_option_by_name('log_level_win_sys_debug'), ELASTIC_APM_LOG_LEVEL_TRACE);
}

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_file')", elastic_apm_get_config_option_by_name('log_level_file'), ELASTIC_APM_LOG_LEVEL_DEBUG);

echo 'Test completed'
?>
--EXPECT--
Test completed
