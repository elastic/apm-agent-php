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

#ifndef ELASTICAPM_PUBLIC_API_H
#define ELASTICAPM_PUBLIC_API_H

#include "php_elasticapm.h"

static inline const char* getCurrentTransactionId()
{
    GlobalState* globalState = getGlobalState();
    const Transaction* const currentTransaction = globalState->currentTransaction;

    return currentTransaction == NULL ? NULL : currentTransaction->id;
}

static inline const char* getCurrentTraceId()
{
    GlobalState* globalState = getGlobalState();
    const Transaction* const currentTransaction = globalState->currentTransaction;

    return currentTransaction == NULL ? NULL : currentTransaction->traceId;
}

#endif /* #ifndef ELASTICAPM_PUBLIC_API_H */
