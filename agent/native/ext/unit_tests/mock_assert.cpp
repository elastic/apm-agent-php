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

#include "mock_assert.h"
#include "elastic_apm_assert.h"
#include <stdarg.h>

static ProductionCodeAssertFailed g_prodCodeAssertFailed = vElasticApmAssertFailed;

void productionCodeAssertFailed(
        const char* filePath
        , unsigned int lineNumber
        , const char* funcName
        , const char* msgPrintfFmt
        , /* msgPrintfFmtArgs */ ...
)
{
    va_list msgPrintfFmtArgs;
    va_start( msgPrintfFmtArgs, msgPrintfFmt );
    g_prodCodeAssertFailed( filePath, lineNumber, funcName, msgPrintfFmt, msgPrintfFmtArgs );
    va_end( msgPrintfFmtArgs );
}

void setProductionCodeAssertFailed( ProductionCodeAssertFailed prodCodeAssertFailed )
{
    ELASTIC_APM_ASSERT_VALID_PTR( (void*)prodCodeAssertFailed );

    g_prodCodeAssertFailed = prodCodeAssertFailed;
}

static UInt64 g_productionCodeAssertFailedCount = 0;

void productionCodeAssertFailedCountingMock(
        const char* filePath
        , unsigned int lineNumber
        , const char* funcName
        , const char* msgPrintfFmt
        , va_list msgPrintfFmtArgs
)
{
    ELASTIC_APM_UNUSED( filePath );
    ELASTIC_APM_UNUSED( lineNumber );
    ELASTIC_APM_UNUSED( funcName );
    ELASTIC_APM_UNUSED( msgPrintfFmt );
    ELASTIC_APM_UNUSED( msgPrintfFmtArgs );

    ++g_productionCodeAssertFailedCount;
}

UInt64 getProductionCodeAssertFailedCount()
{
    return g_productionCodeAssertFailedCount;
}

void resetProductionCodeAssertFailedCount()
{
    g_productionCodeAssertFailedCount = 0;
}
