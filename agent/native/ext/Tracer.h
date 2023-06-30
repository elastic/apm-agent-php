/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

#pragma once

#include <stdbool.h>
#include "ConfigManager.h"
#include "SystemMetrics.h"
#include "elastic_apm_assert.h"
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
};
typedef struct Tracer Tracer;

ResultCode constructTracer( Tracer* tracer );
ResultCode ensureLoggerInitialConfigIsLatest( Tracer* tracer );
ResultCode ensureAllComponentsHaveLatestConfig( Tracer* tracer );
const ConfigSnapshot* getTracerCurrentConfigSnapshot( const Tracer* tracer );
void moveTracerToFailedState( Tracer* tracer );
bool isTracerInFunctioningState( const Tracer* tracer );
void destructTracer( Tracer* tracer );

Tracer* getGlobalTracer();
