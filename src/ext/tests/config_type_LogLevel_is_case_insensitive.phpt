--TEST--
Configuration values of type LogLevel are case insensitive
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=oFF
ELASTIC_APM_LOG_LEVEL=notice
ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG=TRaCe
--INI--
elasticapm.log_level_syslog=INFO
elasticapm.log_level_file=dEbUg
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level')", elasticapm_get_config_option_by_name('log_level'), ELASTICAPM_LOG_LEVEL_NOTICE);

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_stderr')", elasticapm_get_config_option_by_name('log_level_stderr'), ELASTICAPM_LOG_LEVEL_OFF);

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_syslog')", elasticapm_get_config_option_by_name('log_level_syslog'), ELASTICAPM_LOG_LEVEL_INFO);
}

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_win_sys_debug')", elasticapm_get_config_option_by_name('log_level_win_sys_debug'), ELASTICAPM_LOG_LEVEL_TRACE);
}

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_file')", elasticapm_get_config_option_by_name('log_level_file'), ELASTICAPM_LOG_LEVEL_DEBUG);

echo 'Test completed'
?>
--EXPECT--
Test completed
