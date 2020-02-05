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

#ifndef ELASTICAPM_CONFIG_H
#define ELASTICAPM_CONFIG_H

#include <stdbool.h>

#include "elasticapm_assert.h"
#include "ResultCode.h"

struct Config
{
    bool enabled;
    const char* serverUrl;
    const char* secretToken;
    const char* serviceName;
    const char* log;
    int logLevel;
};

typedef struct Config Config;

#define ENABLED_CONFIG_DEFAULT_VALUE (true)
#define ENABLED_CONFIG_DEFAULT_STR_VALUE ("true")
#define SERVER_URL_CONFIG_DEFAULT_VALUE ("http://localhost:8200")
#define SECRET_TOKEN_CONFIG_DEFAULT_VALUE ("")
#define SERVICE_NAME_CONFIG_DEFAULT_VALUE ("Unknown PHP service")
#define LOG_CONFIG_DEFAULT_VALUE ("")
#define LOG_LEVEL_CONFIG_DEFAULT_VALUE (0)
#define LOG_LEVEL_CONFIG_DEFAULT_STR_VALUE ("0")

static inline void cleanupConfig( Config* thisObj )
{
    ASSERT_VALID_PTR( thisObj );

    thisObj->enabled = ENABLED_CONFIG_DEFAULT_VALUE;
    thisObj->serverUrl = SERVER_URL_CONFIG_DEFAULT_VALUE;
    thisObj->secretToken = SECRET_TOKEN_CONFIG_DEFAULT_VALUE;
    thisObj->serviceName = SERVICE_NAME_CONFIG_DEFAULT_VALUE;
    thisObj->log = LOG_CONFIG_DEFAULT_VALUE;
    thisObj->logLevel = LOG_LEVEL_CONFIG_DEFAULT_VALUE;
}

static inline ResultCode initConfig( Config* thisObj )
{
    ASSERT_VALID_PTR( thisObj );

    cleanupConfig( thisObj );

    return resultSuccess;
}

#endif /* #ifndef ELASTICAPM_CONFIG_H */
