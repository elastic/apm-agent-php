--TEST--
Setting configuration options to non-default value (in this case using environment variables)
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=OFF
ELASTIC_APM_ENABLED=0
ELASTIC_APM_LOG_FILE=non-default_log_file_value.txt
ELASTIC_APM_LOG_LEVEL=CRITICAL
ELASTIC_APM_LOG_LEVEL_FILE=TRACE
ELASTIC_APM_LOG_LEVEL_SYSLOG=TRACE
ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG=CRITICAL
ELASTIC_APM_SECRET_TOKEN=non-default_secret_token_123
ELASTIC_APM_SERVER_URL=https://non-default_server_url:4321/some/path
ELASTIC_APM_SERVICE_NAME=Non-default Service Name
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util//bootstrap.php';

//////////////////////////////////////////////
///////////////  enabled

elasticApmAssertSame(
    '0',
    getenv('ELASTIC_APM_ENABLED'),
    "getenv('ELASTIC_APM_ENABLED')"
);

elasticApmAssertSame(
    false,
    elasticapm_is_enabled(),
    "elasticapm_is_enabled()"
);

elasticApmAssertSame(
    false,
    elasticapm_get_config_option_by_name('enabled'),
    "elasticapm_get_config_option_by_name('enabled')"
);

//////////////////////////////////////////////
///////////////  log_file

elasticApmAssertSame(
    'non-default_log_file_value.txt',
    getenv('ELASTIC_APM_LOG_FILE'),
    "getenv('ELASTIC_APM_LOG_FILE')"
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
    getenv('ELASTIC_APM_LOG_LEVEL'),
    "getenv('ELASTIC_APM_LOG_LEVEL')"
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
    getenv('ELASTIC_APM_LOG_LEVEL_FILE'),
    "getenv('ELASTIC_APM_LOG_LEVEL_FILE')"
);

elasticApmAssertSame(
    ELASTICAPM_LOG_LEVEL_TRACE,
    elasticapm_get_config_option_by_name('log_level_file'),
    "elasticapm_get_config_option_by_name('log_level_file')"
);

//////////////////////////////////////////////
///////////////  log_level_syslog

elasticApmAssertSame(
    'TRACE',
    getenv('ELASTIC_APM_LOG_LEVEL_SYSLOG'),
    "getenv('ELASTIC_APM_LOG_LEVEL_SYSLOG')"
);

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame(
        ELASTICAPM_LOG_LEVEL_TRACE,
        elasticapm_get_config_option_by_name('log_level_syslog'),
        "elasticapm_get_config_option_by_name('log_level_syslog')"
    );
}

//////////////////////////////////////////////
///////////////  log_level_win_sys_debug

elasticApmAssertSame(
    'CRITICAL',
    getenv('ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG'),
    "getenv('ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG')"
);

if (elasticApmIsOsWindows()) {
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
    getenv('ELASTIC_APM_SECRET_TOKEN'),
    "getenv('ELASTIC_APM_SECRET_TOKEN')"
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
    getenv('ELASTIC_APM_SERVER_URL'),
    "getenv('ELASTIC_APM_SERVER_URL')"
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
    getenv('ELASTIC_APM_SERVICE_NAME'),
    "getenv('ELASTIC_APM_SERVICE_NAME')"
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
