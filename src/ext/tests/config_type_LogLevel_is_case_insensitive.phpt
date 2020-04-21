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
require __DIR__ . '/bootstrap.php';

elasticApmAssertSame(
    ELASTICAPM_LOG_LEVEL_NOTICE,
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
        ELASTICAPM_LOG_LEVEL_INFO,
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
