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

#ifndef ELASTICAPM_CONFIGURATION_H
#define ELASTICAPM_CONFIGURATION_H

#include <stdbool.h>

struct Config
{
    bool enable;
    const char* host;
    const char* server_url;
    const char* secret_token;
    const char* service_name;
    const char* log;
    int log_level;
};

typedef struct Config Config;

#endif /* #ifndef ELASTICAPM_CONFIGURATION_H */
