--TEST--
Configuration values of type LogLevel: it is enough to provide unambiguous prefix
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=Off
ELASTIC_APM_LOG_LEVEL=warn
ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG=TRa
--INI--
elasticapm.log_level_syslog=Er
elasticapm.log_level_file=dEb
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level')", elasticapm_get_config_option_by_name('log_level'), ELASTICAPM_LOG_LEVEL_WARNING);

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_stderr')", elasticapm_get_config_option_by_name('log_level_stderr'), ELASTICAPM_LOG_LEVEL_OFF);

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_syslog')", elasticapm_get_config_option_by_name('log_level_syslog'), ELASTICAPM_LOG_LEVEL_ERROR);
}

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_win_sys_debug')", elasticapm_get_config_option_by_name('log_level_win_sys_debug'), ELASTICAPM_LOG_LEVEL_TRACE);
}

elasticApmAssertSame("elasticapm_get_config_option_by_name('log_level_file')", elasticapm_get_config_option_by_name('log_level_file'), ELASTICAPM_LOG_LEVEL_DEBUG);

echo 'Test completed'
?>
--EXPECT--
Test completed
