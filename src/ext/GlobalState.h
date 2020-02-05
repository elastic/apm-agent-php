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

#ifndef ELASTICAPM_GLOBAL_STATE_H
#define ELASTICAPM_GLOBAL_STATE_H

#include <stdbool.h>
#include "elasticapm_config.h"
#include "Transaction.h"
#include "SystemMetrics.h"

struct GlobalState
{
    Config config;
    Transaction* currentTransaction;
    SystemMetricsReading startSystemMetricsReading;

    bool iniEntriesRegistered;
    bool curlInited;

    void (* originalZendErrorCallback )( int type, const char* error_filename, const uint32_t error_lineno, const char* format, va_list args );
    bool originalZendErrorCallbackSet;
    void (* originalZendThrowExceptionHook )( zval* exception );
    bool originalZendThrowExceptionHookSet;
};

typedef struct GlobalState GlobalState;

static inline ResultCode initGlobalState( GlobalState* thisObj )
{
    ASSERT_VALID_PTR( thisObj );

    initConfig( &thisObj->config );

    thisObj->currentTransaction = NULL;
    thisObj->iniEntriesRegistered = false;
    thisObj->curlInited = false;
    thisObj->originalZendErrorCallback = NULL;
    thisObj->originalZendErrorCallbackSet = false;
    thisObj->originalZendThrowExceptionHook = NULL;
    thisObj->originalZendThrowExceptionHookSet = false;

    return resultSuccess;
}

static inline void cleanupGlobalState( GlobalState* thisObj )
{
    ASSERT_VALID_PTR( thisObj );

    cleanupConfig( &thisObj->config );
}

#endif /* #ifndef ELASTICAPM_GLOBAL_STATE_H */
