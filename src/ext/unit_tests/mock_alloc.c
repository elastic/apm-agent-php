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

#include <stdbool.h>
#include <stdlib.h>
#include "basic_macros.h"
#include "unit_test_util.h"
#include "mock_log_custom_sink.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_C_EXT_UNIT_TESTS

/**
 * productionCodePeMalloc is used in "elastic_apm_alloc.h"
 * via ELASTIC_APM_PEMALLOC_FUNC defined in unit tests' CMakeLists.txt
 */
void* productionCodePeMalloc( size_t requestedSize, bool isPersistent )
{
    ELASTIC_APM_UNUSED( isPersistent );
    return malloc( requestedSize );
}
ELASTIC_APM_SUPPRESS_UNUSED( productionCodePeMalloc );

/**
 * productionCodePeFree is used in "elastic_apm_alloc.h"
 * via ELASTIC_APM_PEFREE_FUNC defined in unit tests' CMakeLists.txt
 */
void productionCodePeFree( void* allocatedBlock, bool isPersistent )
{
    ELASTIC_APM_UNUSED( isPersistent );
    free( allocatedBlock );
}
ELASTIC_APM_SUPPRESS_UNUSED( productionCodePeFree );

/**
 * onMemoryLeakDuringUnitTests is used in "MemoryTracker.c"
 * via ELASTIC_APM_ON_MEMORY_LEAK_CUSTOM_FUNC defined in unit tests' CMakeLists.txt
 */
void onMemoryLeakDuringUnitTests()
{
    ELASTIC_APM_FORCE_LOG_CRITICAL( "The last test will be considered FAILED because of the detected memory leak." );
    ELASTIC_APM_CMOCKA_FAIL();
}
ELASTIC_APM_SUPPRESS_UNUSED( onMemoryLeakDuringUnitTests );
