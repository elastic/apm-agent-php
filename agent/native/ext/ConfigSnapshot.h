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

#include "ConfigSnapshot_forward_decl.h"
#include <stdbool.h>
#include "basic_types.h" // String
#include "LogLevel.h"
#include "OptionalBool.h"
#include "time_util.h" // Duration
#include "elastic_apm_assert_enabled.h"

struct ConfigSnapshot
{
    bool abortOnMemoryLeak = false;
        #ifdef PHP_WIN32
    bool allowAbortDialog;
        #endif
        #if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )
    AssertLevel assertLevel = assertLevel_off;
        #endif
    String apiKey = nullptr;
    bool astProcessEnabled = false;
    bool astProcessDebugDumpConvertedBackToSource = false;
    String astProcessDebugDumpForPathPrefix = nullptr;
    String astProcessDebugDumpOutDir = nullptr;
    OptionalBool asyncBackendComm = {false, false};
    String bootstrapPhpPartFile = nullptr;
    bool breakdownMetrics = false;
    bool captureErrors = false;
    String devInternal = nullptr;
    bool devInternalBackendCommLogVerbose = false;
    String disableInstrumentations = nullptr;
    bool disableSend = false;
    bool enabled = false;
    String environment = nullptr;
    String globalLabels = nullptr;
    String hostname = nullptr;
    InternalChecksLevel internalChecksLevel = internalChecksLevel_off;
    String logFile = nullptr;
    LogLevel logLevel = logLevel_off;
    LogLevel logLevelFile = logLevel_off;
    LogLevel logLevelStderr = logLevel_off;
        #ifndef PHP_WIN32
    LogLevel logLevelSyslog = logLevel_off;
        #endif
        #ifdef PHP_WIN32
    LogLevel logLevelWinSysDebug = logLevel_off;
        #endif
        #if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )
    MemoryTrackingLevel memoryTrackingLevel = memoryTrackingLevel_off;
        #endif
    String nonKeywordStringMaxLength = nullptr;
    bool profilingInferredSpansEnabled = false;
    String profilingInferredSpansMinDuration = nullptr;
    String profilingInferredSpansSamplingInterval = nullptr;
    String sanitizeFieldNames = nullptr;
    String secretToken = nullptr;
    String serverUrl = nullptr;
    Duration serverTimeout;
    String serviceName = nullptr;
    String serviceNodeName = nullptr;
    String serviceVersion = nullptr;
    bool spanCompressionEnabled = false;
    String spanCompressionExactMatchMaxDuration = nullptr;
    String spanCompressionSameKindMaxDuration = nullptr;
    String spanStackTraceMinDuration = nullptr;
    String stackTraceLimit = nullptr;
    String transactionIgnoreUrls = nullptr;
    String transactionMaxSpans = nullptr;
    String transactionSampleRate = nullptr;
    String urlGroups = nullptr;
    bool verifyServerCert = false;
};
