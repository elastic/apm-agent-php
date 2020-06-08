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

#define ELASTICAPM_LOG_CATEGORY_C_EXT_UNIT_TESTS "C-Ext Unit tests"
