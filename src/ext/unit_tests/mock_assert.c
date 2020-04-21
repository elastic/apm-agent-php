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

static ProductionCodeAssertFailed g_prodCodeAssertFailed = elasticApmAssertFailed;

void productionCodeAssertFailed(
        const char* condExpr,
        const char* fileName,
        unsigned int lineNumber,
        const char* funcName,
        const char* msg )
{
    g_prodCodeAssertFailed( condExpr, fileName, lineNumber, funcName, msg );
}

void setProductionCodeAssertFailed( ProductionCodeAssertFailed prodCodeAssertFailed )
{
    ELASTICAPM_ASSERT_VALID_PTR( prodCodeAssertFailed );

    g_prodCodeAssertFailed = prodCodeAssertFailed;
}

static UInt64 g_productionCodeAssertFailedCount = 0;

void productionCodeAssertFailedCountingMock(
        const char* condExpr,
        const char* fileName,
        unsigned int lineNumber,
        const char* funcName,
        const char* msg )
{
    ELASTICAPM_UNUSED( condExpr );
    ELASTICAPM_UNUSED( fileName );
    ELASTICAPM_UNUSED( lineNumber );
    ELASTICAPM_UNUSED( funcName );
    ELASTICAPM_UNUSED( msg );

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
