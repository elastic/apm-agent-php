--TEST--
Setting configuration options to non-default value (in this case using ini file)
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=OFF
--INI--
elasticapm.enabled=0
elasticapm.log_file=non-default_log_file_value.txt
elasticapm.log_level=CRITICAL
elasticapm.log_level_file=TRACE
elasticapm.log_level_syslog=TRACE
elasticapm.log_level_win_sys_debug=CRITICAL
elasticapm.secret_token=non-default_secret_token_123
elasticapm.server_url=https://non-default_server_url:4321/some/path
elasticapm.service_name=Non-default Service Name
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util//bootstrap.php';

//////////////////////////////////////////////
///////////////  enabled

elasticApmAssertEqual(
    false,
    ini_get('elasticapm.enabled'),
    "ini_get('elasticapm.enabled')"
);

elasticApmAssertSame(
    false,
    elasticapm_get_config_option_by_name('enabled'),
    "elasticapm_get_config_option_by_name('enabled')"
);

elasticApmAssertSame(
    false,
    elasticapm_is_enabled(),
    "elasticapm_is_enabled()"
);

//////////////////////////////////////////////
///////////////  log_file

elasticApmAssertSame(
    'non-default_log_file_value.txt',
    ini_get('elasticapm.log_file'),
    "ini_get('elasticapm.log_file')"
);

elasticApmAssertSame(
    'non-default_log_file_value.txt',
    elasticapm_get_config_option_by_name('log_file'),
    "elasticapm_get_config_option_by_name('log_file')"
);

//////////////////////////////////////////////
///////////////  log_level

elasticApmAssertSame(
    'CRITICAL',
    ini_get('elasticapm.log_level'),
    "ini_get('elasticapm.log_level')"
);

elasticApmAssertSame(
    ELASTICAPM_LOG_LEVEL_CRITICAL,
    elasticapm_get_config_option_by_name('log_level'),
    "elasticapm_get_config_option_by_name('log_level')"
);

//////////////////////////////////////////////
///////////////  log_level_file

elasticApmAssertSame(
    'TRACE',
    ini_get('elasticapm.log_level_file'),
    "ini_get('elasticapm.log_level_file')"
);

elasticApmAssertSame(
    ELASTICAPM_LOG_LEVEL_TRACE,
    elasticapm_get_config_option_by_name('log_level_file'),
    "elasticapm_get_config_option_by_name('log_level_file')"
);

//////////////////////////////////////////////
///////////////  log_level_syslog

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame(
        'TRACE',
        ini_get('elasticapm.log_level_syslog'),
        "ini_get('elasticapm.log_level_syslog')"
    );

    elasticApmAssertSame(
        ELASTICAPM_LOG_LEVEL_TRACE,
        elasticapm_get_config_option_by_name('log_level_syslog'),
        "elasticapm_get_config_option_by_name('log_level_syslog')"
    );
}

//////////////////////////////////////////////
///////////////  log_level_win_sys_debug

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame(
        'CRITICAL',
        ini_get('elasticapm.log_level_win_sys_debug'),
        "ini_get('elasticapm.log_level_win_sys_debug')"
    );
    elasticApmAssertSame(
        ELASTICAPM_LOG_LEVEL_CRITICAL,
        elasticapm_get_config_option_by_name('log_level_win_sys_debug'),
        "elasticapm_get_config_option_by_name('log_level_win_sys_debug')"
    );
}

//////////////////////////////////////////////
///////////////  secret_token

elasticApmAssertSame(
    'non-default_secret_token_123',
    ini_get('elasticapm.secret_token'),
    "ini_get('elasticapm.secret_token')"
);

elasticApmAssertSame(
    'non-default_secret_token_123',
    elasticapm_get_config_option_by_name('secret_token'),
    "elasticapm_get_config_option_by_name('secret_token')"
);

//////////////////////////////////////////////
///////////////  server_url

elasticApmAssertSame(
    'https://non-default_server_url:4321/some/path',
    ini_get('elasticapm.server_url'),
    "ini_get('elasticapm.server_url')"
);

elasticApmAssertSame(
    'https://non-default_server_url:4321/some/path',
    elasticapm_get_config_option_by_name('server_url'),
    "elasticapm_get_config_option_by_name('server_url')"
);

//////////////////////////////////////////////
///////////////  service_name

elasticApmAssertSame(
    'Non-default Service Name',
    ini_get('elasticapm.service_name'),
    "ini_get('elasticapm.service_name')"
);

elasticApmAssertSame(
    'Non-default Service Name',
    elasticapm_get_config_option_by_name('service_name'),
    "elasticapm_get_config_option_by_name('service_name')"
);

echo 'Test completed'
?>
--EXPECT--
Test completed
