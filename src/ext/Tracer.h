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
#include "SystemMetrics.h"
#include "elastic_apm_assert.h"
#include "log.h"
#include "internal_checks.h"
#include "MemoryTracker.h"
#include "RequestScoped.h"

struct IniEntriesRegistrationState
{
    bool entriesRegistered;
};
typedef struct IniEntriesRegistrationState IniEntriesRegistrationState;

struct Tracer
{
        #if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )
    AssertLevel currentAssertLevel;
        #endif
    InternalChecksLevel currentInternalChecksLevel;

    Logger logger;
        #if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )
    MemoryTracker memTracker;
        #endif
    ConfigManager* configManager;
    SystemMetricsReading startSystemMetricsReading;

    bool isInited;
    bool isFailed;
    IniEntriesRegistrationState iniEntriesRegistrationState;
    bool curlInited;

    RequestScoped requestScoped;
};
typedef struct Tracer Tracer;

ResultCode constructTracer( Tracer* tracer );
ResultCode ensureAllComponentsHaveLatestConfig( Tracer* tracer );
const ConfigSnapshot* getTracerCurrentConfigSnapshot( const Tracer* tracer );
void moveTracerToFailedState( Tracer* tracer );
bool isTracerInFunctioningState( const Tracer* tracer );
void destructTracer( Tracer* tracer );

Tracer* getGlobalTracer();
