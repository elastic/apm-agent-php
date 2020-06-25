--TEST--
Configuration values of type LogLevel are case insensitive
--SKIPIF--
<?php if ( ! extension_loaded( 'elastic_apm' ) ) die( 'skip'.'Extension elastic_apm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=oFF
ELASTIC_APM_LOG_LEVEL=notice
ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG=TRaCe
--INI--
elastic_apm.log_level_syslog=INFO
elastic_apm.log_level_file=dEbUg
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level')", elastic_apm_get_config_option_by_name('log_level'), ELASTIC_APM_LOG_LEVEL_NOTICE);

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_stderr')", elastic_apm_get_config_option_by_name('log_level_stderr'), ELASTIC_APM_LOG_LEVEL_OFF);

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_syslog')", elastic_apm_get_config_option_by_name('log_level_syslog'), ELASTIC_APM_LOG_LEVEL_INFO);
}

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_win_sys_debug')", elastic_apm_get_config_option_by_name('log_level_win_sys_debug'), ELASTIC_APM_LOG_LEVEL_TRACE);
}

elasticApmAssertSame("elastic_apm_get_config_option_by_name('log_level_file')", elastic_apm_get_config_option_by_name('log_level_file'), ELASTIC_APM_LOG_LEVEL_DEBUG);

echo 'Test completed'
?>
--EXPECT--
Test completed
