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
#include "DynamicArray.h"

void setGlobalLoggerLevelForCustomSink( LogLevel levelForCustomSink );

/// Use two phase approach (i.e., init+enable vs just init)
/// to correctly work with MemoryTracker.
/// When MockLogCustomSink is init-ed but not yet enabled it just discards all log statements it receives.
/// Thus it doesn't allocate any memory which would otherwise been tracked incorrectly by MemoryTracker
/// because MockLogCustomSink is init-ed before MemoryTracker
struct MockLogCustomSink;
typedef struct MockLogCustomSink MockLogCustomSink;

MockLogCustomSink* getGlobalMockLogCustomSink();

void initMockLogCustomSink( MockLogCustomSink* mockLogCustomSink );
void enableMockLogCustomSink( MockLogCustomSink* mockLogCustomSink );
void disableMockLogCustomSink( MockLogCustomSink* mockLogCustomSink );
void uninitMockLogCustomSink( MockLogCustomSink* mockLogCustomSink );
size_t numberOfStatementsInMockLogCustomSink( const MockLogCustomSink* mockLogCustomSink );
String getStatementInMockLogCustomSinkContent( const MockLogCustomSink* mockLogCustomSink, size_t index );
void clearMockLogCustomSink( MockLogCustomSink* mockLogCustomSink );

#define ELASTIC_APM_LOG_CATEGORY_C_EXT_UNIT_TESTS "C-Ext Unit tests"
