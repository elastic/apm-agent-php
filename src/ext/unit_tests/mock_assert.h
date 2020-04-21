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

#include "basic_types.h"
#include "basic_macros.h"

/**
 * productionCodeAssertFailed is used in "elasticapm_assert.h"
 * via ELASTICAPM_ASSERT_FAILED_FUNC defined in unit tests' CMakeLists.txt
 */
void productionCodeAssertFailed(
        const char* condExpr,
        const char* fileName,
        unsigned int lineNumber,
        const char* funcName,
        const char* msg );

ELASTICAPM_SUPPRESS_UNUSED( productionCodeAssertFailed );

typedef void (* ProductionCodeAssertFailed )(
        const char* condExpr,
        const char* fileName,
        UInt lineNumber,
        const char* funcName,
        const char* msg );
void setProductionCodeAssertFailed( ProductionCodeAssertFailed prodCodeAssertFailed );

void productionCodeAssertFailedCountingMock(
        const char* condExpr,
        const char* fileName,
        UInt lineNumber,
        const char* funcName,
        const char* msg );
UInt64 getProductionCodeAssertFailedCount();
void resetProductionCodeAssertFailedCount();
