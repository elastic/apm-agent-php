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

#include "mock_log_custom_sink.h"
#include "unit_test_util.h"

void setGlobalLoggerLevelForCustomSink( LogLevel levelForCustomSink )
{
    LoggerConfig newConfig;
    ELASTIC_APM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( reconfigureLogger( getGlobalLogger(), &newConfig, /* generalLevel: */ logLevel_off ) );
    getGlobalLogger()->maxEnabledLevel = levelForCustomSink;
}

static MockLogCustomSink g_mockLogCustomSink;

MockLogCustomSink &getGlobalMockLogCustomSink() {
    return g_mockLogCustomSink;
}

/// writeToMockLogCustomSink is used in "log.c"
/// via ELASTIC_APM_LOG_CUSTOM_SINK_FUNC defined in unit tests' CMakeLists.txt
void writeToMockLogCustomSink( String text )
{
    // When MockLogCustomSink is init-ed but not yet enabled it just discards all log statements it receives.
    if (!getGlobalMockLogCustomSink().enabled()) {
        return;
    }
    getGlobalMockLogCustomSink().push_back(text);
}

ELASTIC_APM_SUPPRESS_UNUSED( writeToMockLogCustomSink );
