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
#include "ConfigManager.h"
#include "Transaction.h"
#include "SystemMetrics.h"
#include "elasticapm_assert.h"
#include "log.h"
#include "internal_checks.h"
#include "MemoryTracker.h"


struct IniEntriesRegistrationState
{
    bool entriesRegistered;
};
typedef struct IniEntriesRegistrationState IniEntriesRegistrationState;

struct Tracer
{
        #if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )
    AssertLevel currentAssertLevel;
        #endif
    InternalChecksLevel currentInternalChecksLevel;

    Logger logger;
        #if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )
    MemoryTracker memTracker;
        #endif
    ConfigManager* configManager;
    Transaction* currentTransaction;
    SystemMetricsReading startSystemMetricsReading;

    bool isInited;
    IniEntriesRegistrationState iniEntriesRegistrationState;
    bool curlInited;

    void (* originalZendErrorCallback )( int type, String error_filename, uint32_t error_lineno, String format, va_list args );
    bool originalZendErrorCallbackSet;
    void (* originalZendThrowExceptionHook )( zval* exception );
    bool originalZendThrowExceptionHookSet;
};
typedef struct Tracer Tracer;

ResultCode constructTracer( Tracer* tracer );
ResultCode ensureAllComponentsHaveLatestConfig( Tracer* tracer );
const ConfigSnapshot* getTracerCurrentConfigSnapshot( Tracer* tracer );
void destructTracer( Tracer* tracer );

Tracer* getGlobalTracer();
