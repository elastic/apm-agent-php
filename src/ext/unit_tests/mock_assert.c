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

#include "mock_assert.h"
#include "elasticapm_assert.h"
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
    ELASTICAPM_ASSERT_VALID_PTR( prodCodeAssertFailed );

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
    ELASTICAPM_UNUSED( filePath );
    ELASTICAPM_UNUSED( lineNumber );
    ELASTICAPM_UNUSED( funcName );
    ELASTICAPM_UNUSED( msgPrintfFmt );
    ELASTICAPM_UNUSED( msgPrintfFmtArgs );

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
