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

#include "php_elasticapm.h"

static inline const char* getCurrentTransactionId()
{
    const Transaction* const currentTransaction = getGlobalState()->currentTransaction;
    return currentTransaction == NULL ? NULL : currentTransaction->id;
}

static inline const char* getCurrentTraceId()
{
    const Transaction* const currentTransaction = getGlobalState()->currentTransaction;
    return currentTransaction == NULL ? NULL : currentTransaction->traceId;
}

static inline bool isEnabled()
{
    return getGlobalState()->config.enabled;
}
