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

elasticApmAssertSame(
    ELASTICAPM_LOG_LEVEL_WARNING,
    elasticapm_get_config_option_by_name('log_level'),
    "elasticapm_get_config_option_by_name('log_level')"
);

elasticApmAssertSame(
    ELASTICAPM_LOG_LEVEL_OFF,
    elasticapm_get_config_option_by_name('log_level_stderr'),
    "elasticapm_get_config_option_by_name('log_level_stderr')"
);

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame(
        ELASTICAPM_LOG_LEVEL_ERROR,
        elasticapm_get_config_option_by_name('log_level_syslog'),
        "elasticapm_get_config_option_by_name('log_level_syslog')"
    );
}

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame(
        ELASTICAPM_LOG_LEVEL_TRACE,
        elasticapm_get_config_option_by_name('log_level_win_sys_debug'),
        "elasticapm_get_config_option_by_name('log_level_win_sys_debug')"
    );
}

elasticApmAssertSame(
    ELASTICAPM_LOG_LEVEL_DEBUG,
    elasticapm_get_config_option_by_name('log_level_file'),
    "elasticapm_get_config_option_by_name('log_level_file')"
);

echo 'Test completed'
?>
--EXPECT--
Test completed
