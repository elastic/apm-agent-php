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
    bool abortOnMemoryLeak;
        #ifdef PHP_WIN32
    bool allowAbortDialog;
        #endif
        #if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )
    AssertLevel assertLevel;
        #endif
    String apiKey;
    bool astProcessEnabled;
    bool astProcessDebugDumpConvertedBackToSource;
    String astProcessDebugDumpForPathPrefix;
    String astProcessDebugDumpOutDir;
    OptionalBool asyncBackendComm;
    String bootstrapPhpPartFile;
    bool breakdownMetrics;
    bool captureErrors;
    String devInternal;
    bool devInternalBackendCommLogVerbose;
    String disableInstrumentations;
    bool disableSend;
    bool enabled;
    String environment;
    String globalLabels;
    String hostname;
    InternalChecksLevel internalChecksLevel;
    String logFile;
    LogLevel logLevel;
    LogLevel logLevelFile;
    LogLevel logLevelStderr;
        #ifndef PHP_WIN32
    LogLevel logLevelSyslog;
        #endif
        #ifdef PHP_WIN32
    LogLevel logLevelWinSysDebug;
        #endif
        #if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )
    MemoryTrackingLevel memoryTrackingLevel;
        #endif
    String nonKeywordStringMaxLength;
    bool profilingInferredSpansEnabled;
    String profilingInferredSpansMinDuration;
    String profilingInferredSpansSamplingInterval;
    String sanitizeFieldNames;
    String secretToken;
    String serverUrl;
    Duration serverTimeout;
    String serviceName;
    String serviceNodeName;
    String serviceVersion;
    bool spanCompressionEnabled;
    String spanCompressionExactMatchMaxDuration;
    String spanCompressionSameKindMaxDuration;
    String spanStackTraceMinDuration;
    String stackTraceLimit;
    String transactionIgnoreUrls;
    String transactionMaxSpans;
    String transactionSampleRate;
    String urlGroups;
    bool verifyServerCert;
};
