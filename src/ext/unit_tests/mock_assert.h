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

#include <stdarg.h>
#include "basic_types.h"
#include "basic_macros.h"

/**
 * productionCodeAssertFailed is used in "elastic_apm_assert.h"
 * via ELASTIC_APM_ASSERT_FAILED_FUNC defined in unit tests' CMakeLists.txt
 */
void productionCodeAssertFailed(
        const char* filePath /* <- argument #1 */
        , unsigned int lineNumber
        , const char* funcName
        , const char* msgPrintfFmt /* <- printf format is argument #4 */
        , /* msgPrintfFmtArgs */ ... /* <- arguments for printf format placeholders start from argument #5 */
) ELASTIC_APM_PRINTF_ATTRIBUTE( /* printfFmtPos: */ 4, /* printfFmtArgsPos: */ 5 );

ELASTIC_APM_SUPPRESS_UNUSED( productionCodeAssertFailed );

typedef void (* ProductionCodeAssertFailed )(
        const char* filePath
        , unsigned int lineNumber
        , const char* funcName
        , const char* msgPrintfFmt
        , va_list msgPrintfFmtArgs
);

void setProductionCodeAssertFailed( ProductionCodeAssertFailed prodCodeAssertFailed );

void productionCodeAssertFailedCountingMock(
        const char* filePath
        , unsigned int lineNumber
        , const char* funcName
        , const char* msgPrintfFmt
        , va_list msgPrintfFmtArgs
);
UInt64 getProductionCodeAssertFailedCount();
void resetProductionCodeAssertFailedCount();
