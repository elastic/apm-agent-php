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

#include <stdarg.h>
#include "basic_types.h"
#include "basic_macros.h"

/**
 * productionCodeAssertFailed is used in "elasticapm_assert.h"
 * via ELASTICAPM_ASSERT_FAILED_FUNC defined in unit tests' CMakeLists.txt
 */
void productionCodeAssertFailed(
        const char* filePath /* <- argument #1 */
        , unsigned int lineNumber
        , const char* funcName
        , const char* msgPrintfFmt /* <- printf format is argument #4 */
        , /* msgPrintfFmtArgs */ ... /* <- arguments for printf format placeholders start from argument #5 */
) ELASTICAPM_PRINTF_ATTRIBUTE( /* printfFmtPos: */ 4, /* printfFmtArgsPos: */ 5 );

ELASTICAPM_SUPPRESS_UNUSED( productionCodeAssertFailed );

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
