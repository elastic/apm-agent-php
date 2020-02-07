/*
   +----------------------------------------------------------------------+
   | Elastic APM agent for PHP                                            |
   +----------------------------------------------------------------------+
   | Copyright (c) 2020 Elasticsearch B.V.                                |
   +----------------------------------------------------------------------+
   | Elasticsearch B.V. licenses this file under the Apache 2.0 License.  |
   | See the LICENSE file in the project root for more information.       |
   +----------------------------------------------------------------------+
 */

#pragma once

#include <stdbool.h>

#include "elasticapm_assert.h"
#include "ResultCode.h"
#include "utils.h"
#include "log.h"

struct Config
{
    bool enabled;
    const char* serverUrl;
    const char* secretToken;
    const char* serviceName;
    const char* logFile;
    int logLevel;
};

typedef struct Config Config;

// We don't wrap value in parenthesis because it's stringized later
#define ENABLED_CONFIG_DEFAULT_VALUE true
#define ENABLED_CONFIG_DEFAULT_STR_VALUE ( ELASTIC_PP_STRINGIZE( ENABLED_CONFIG_DEFAULT_VALUE ) )
#define SERVER_URL_CONFIG_DEFAULT_VALUE ( "http://localhost:8200" )
#define SECRET_TOKEN_CONFIG_DEFAULT_VALUE ( "" )
#define SERVICE_NAME_CONFIG_DEFAULT_VALUE ( "Unknown PHP service" )
#define LOG_CONFIG_DEFAULT_VALUE ( "" )
// We don't wrap value in parenthesis because it's stringized later
#define LOG_LEVEL_CONFIG_DEFAULT_VALUE 0
#define LOG_LEVEL_CONFIG_DEFAULT_STR_VALUE ( ELASTIC_PP_STRINGIZE( LOG_LEVEL_CONFIG_DEFAULT_VALUE ) )

static inline void cleanupConfig( Config* thisObj )
{
    ASSERT_VALID_PTR( thisObj );

    thisObj->enabled = ENABLED_CONFIG_DEFAULT_VALUE;
    thisObj->serverUrl = SERVER_URL_CONFIG_DEFAULT_VALUE;
    thisObj->secretToken = SECRET_TOKEN_CONFIG_DEFAULT_VALUE;
    thisObj->serviceName = SERVICE_NAME_CONFIG_DEFAULT_VALUE;
    thisObj->logFile = LOG_CONFIG_DEFAULT_VALUE;
    thisObj->logLevel = LOG_LEVEL_CONFIG_DEFAULT_VALUE;
}

static inline ResultCode initConfig( Config* thisObj )
{
    ASSERT_VALID_PTR( thisObj );

    cleanupConfig( thisObj );

    return resultSuccess;
}
