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
#include "log.h"
#include "basic_types.h"
#include <string>
#include <vector>
void setGlobalLoggerLevelForCustomSink( LogLevel levelForCustomSink );

/// Use two phase approach (i.e., init+enable vs just init)
/// to correctly work with MemoryTracker.
/// When MockLogCustomSink is init-ed but not yet enabled it just discards all log statements it receives.
/// Thus it doesn't allocate any memory which would otherwise been tracked incorrectly by MemoryTracker
/// because MockLogCustomSink is init-ed before MemoryTracker

class MockLogCustomSink {
public:
    void enable() {
        isEnabled = true;
    }

    void disable() {
        isEnabled = false;
    }

    void reset() {
        isEnabled = false;
        statements.clear();
    }

    bool enabled() const {
        return isEnabled;
    }

    size_t size() const {
        return statements.size();
    }

    void clear() {
        statements.clear();
    }

    const std::string &get(size_t index) const {
        return statements[index];
    }

    void push_back(std::string text) {

        statements.emplace_back(std::move(text));
    }

private:
    bool isEnabled = false;
    std::vector<std::string> statements;
};

MockLogCustomSink &getGlobalMockLogCustomSink();

#define ELASTIC_APM_LOG_CATEGORY_C_EXT_UNIT_TESTS "C-Ext Unit tests"
