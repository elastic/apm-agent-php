--TEST--
Verify configuration option's defaults
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=OFF
ELASTIC_APM_ENABLED=
ELASTIC_APM_LOG_FILE=
ELASTIC_APM_LOG_LEVEL=
ELASTIC_APM_LOG_LEVEL_FILE=
ELASTIC_APM_LOG_LEVEL_SYSLOG=
ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG=
ELASTIC_APM_SECRET_TOKEN=
ELASTIC_APM_SERVER_URL=
ELASTIC_APM_SERVICE_NAME=
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

//////////////////////////////////////////////
///////////////  enabled

elasticApmAssertSame(
    false,
    getenv('ELASTIC_APM_ENABLED'),
    "getenv('ELASTIC_APM_ENABLED')"
);

elasticApmAssertEqual(
    false,
    ini_get('elasticapm.enabled'),
    "ini_get('elasticapm.enabled')"
);

elasticApmAssertSame(
    true,
    elasticapm_is_enabled(),
    "elasticapm_is_enabled()"
);

elasticApmAssertSame(
    true,
    elasticapm_get_config_option_by_name('enabled'),
    "elasticapm_get_config_option_by_name('enabled')"
);

//////////////////////////////////////////////
///////////////  log_file

elasticApmAssertSame(
    false,
    getenv('ELASTIC_APM_LOG_FILE'),
    "getenv('ELASTIC_APM_LOG_FILE')"
);

elasticApmAssertEqual(
    false,
    ini_get('elasticapm.log_file'),
    "ini_get('elasticapm.log_file')"
);

elasticApmAssertSame(
    null,
    elasticapm_get_config_option_by_name('log_file'),
    "elasticapm_get_config_option_by_name('log_file')"
);

//////////////////////////////////////////////
///////////////  log_level

elasticApmAssertSame(
    false,
    getenv('ELASTIC_APM_LOG_LEVEL'),
    "getenv('ELASTIC_APM_LOG_LEVEL')"
);

elasticApmAssertEqual(
    false,
    ini_get('elasticapm.log_level'),
    "ini_get('elasticapm.log_level')"
);

elasticApmAssertSame(
    ELASTICAPM_LOG_LEVEL_NOT_SET,
    elasticapm_get_config_option_by_name('log_level'),
    "elasticapm_get_config_option_by_name('log_level')"
);

//////////////////////////////////////////////
///////////////  log_level_file

elasticApmAssertSame(
    false,
    getenv('ELASTIC_APM_LOG_LEVEL_FILE'),
    "getenv('ELASTIC_APM_LOG_LEVEL_FILE')"
);

elasticApmAssertEqual(
    false,
    ini_get('elasticapm.log_level_file'),
    "ini_get('elasticapm.log_level_file')"
);

elasticApmAssertSame(
    ELASTICAPM_LOG_LEVEL_NOT_SET,
    elasticapm_get_config_option_by_name('log_level_file'),
    "elasticapm_get_config_option_by_name('log_level_file')"
);

//////////////////////////////////////////////
///////////////  log_level_syslog

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame(
        false,
        getenv('ELASTIC_APM_LOG_LEVEL_SYSLOG'),
        "getenv('ELASTIC_APM_LOG_LEVEL_SYSLOG')"
    );

    elasticApmAssertEqual(
        false,
        ini_get('elasticapm.log_level_syslog'),
        "ini_get('elasticapm.log_level_syslog')"
    );

    elasticApmAssertSame(
        ELASTICAPM_LOG_LEVEL_NOT_SET,
        elasticapm_get_config_option_by_name('log_level_syslog'),
        "elasticapm_get_config_option_by_name('log_level_syslog')"
    );
}

//////////////////////////////////////////////
///////////////  log_level_win_sys_debug

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame(
        false,
        getenv('ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG'),
        "getenv('ELASTIC_APM_LOG_LEVEL_WIN_SYS_DEBUG')"
    );

    elasticApmAssertEqual(
        false,
        ini_get('elasticapm.log_level_win_sys_debug'),
        "ini_get('elasticapm.log_level_win_sys_debug')"
    );

    elasticApmAssertSame(
        ELASTICAPM_LOG_LEVEL_NOT_SET,
        elasticapm_get_config_option_by_name('log_level_win_sys_debug'),
        "elasticapm_get_config_option_by_name('log_level_win_sys_debug')"
    );
}

//////////////////////////////////////////////
///////////////  secret_token

elasticApmAssertSame(
    false,
    getenv('ELASTIC_APM_SECRET_TOKEN'),
    "getenv('ELASTIC_APM_SECRET_TOKEN')"
);

elasticApmAssertEqual(
    false,
    ini_get('elasticapm.secret_token'),
    "ini_get('elasticapm.secret_token')"
);

elasticApmAssertSame(
    null,
    elasticapm_get_config_option_by_name('secret_token'),
    "elasticapm_get_config_option_by_name('secret_token')"
);

//////////////////////////////////////////////
///////////////  server_url

elasticApmAssertSame(
    false,
    getenv('ELASTIC_APM_SERVER_URL'),
    "getenv('ELASTIC_APM_SERVER_URL')"
);

elasticApmAssertEqual(
    false,
    ini_get('elasticapm.server_url'),
    "ini_get('elasticapm.server_url')"
);

elasticApmAssertSame(
    'http://localhost:8200',
    elasticapm_get_config_option_by_name('server_url'),
    "elasticapm_get_config_option_by_name('server_url')"
);

//////////////////////////////////////////////
///////////////  service_name

elasticApmAssertSame(
    false,
    getenv('ELASTIC_APM_SERVICE_NAME'),
    "getenv('ELASTIC_APM_SERVICE_NAME')"
);

elasticApmAssertEqual(
    false,
    ini_get('elasticapm.service_name'),
    "ini_get('elasticapm.service_name')"
);

elasticApmAssertSame(
    'Unknown PHP service',
    elasticapm_get_config_option_by_name('service_name'),
    "elasticapm_get_config_option_by_name('service_name')"
);

echo 'Test completed'
?>
--EXPECT--
Test completed
