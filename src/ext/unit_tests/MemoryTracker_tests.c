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

#include "cmocka_wrapped_for_unit_tests.h"
#include "unit_test_util.h"
#include "elasticapm_alloc.h"

#if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )

static
size_t evaluateAllocationSize( size_t retVal, bool* isCalled )
{
    ELASTICAPM_ASSERT_VALID_PTR( isCalled );
    ELASTICAPM_CMOCKA_ASSERT( ! *isCalled );

    *isCalled = true;
    return retVal;
}

static
void when_enabled_size_arg_to_free_should_be_evaluated_only_once( void** testFixtureState )
{
    ELASTICAPM_UNUSED( testFixtureState );

    ResultCode resultCode;
    char* dummyStr = NULL;
    bool isCalled = false;

    ELASTICAPM_CMOCKA_ASSERT( isMemoryTrackingEnabled( getGlobalMemoryTracker() ) );
    const UInt64 allocatedPersistentBefore = getGlobalMemoryTracker()->allocatedPersistent;
    const UInt64 allocatedRequestScopedBefore = getGlobalMemoryTracker()->allocatedRequestScoped;

    ELASTICAPM_EMALLOC_STRING_IF_FAILED_GOTO( 123, dummyStr );
    // +1 for terminating '\0'
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMemoryTracker()->allocatedPersistent, allocatedPersistentBefore );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMemoryTracker()->allocatedRequestScoped, allocatedRequestScopedBefore + 123 );

    resultCode = resultSuccess;

    finally:
    ELASTICAPM_EFREE_STRING_AND_SET_TO_NULL( evaluateAllocationSize( 123, &isCalled ), dummyStr );
    ELASTICAPM_CMOCKA_ASSERT( isCalled );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMemoryTracker()->allocatedPersistent, allocatedPersistentBefore );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMemoryTracker()->allocatedRequestScoped, allocatedRequestScopedBefore );

    ELASTICAPM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( resultCode );
    return;

    failure:
    goto finally;
}

static
void when_disabled_size_arg_to_free_should_not_be_evaluated( void** testFixtureState )
{
    ELASTICAPM_UNUSED( testFixtureState );

    ResultCode resultCode;
    char* dummyStr = NULL;
    bool isCalled = false;

    ELASTICAPM_CMOCKA_ASSERT( isMemoryTrackingEnabled( getGlobalMemoryTracker() ) );
    const UInt64 allocatedPersistentBefore = getGlobalMemoryTracker()->allocatedPersistent;
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMemoryTracker()->allocatedRequestScoped, 0 );

    ELASTICAPM_PEMALLOC_STRING_IF_FAILED_GOTO( 123, dummyStr );
    // +1 for terminating '\0'
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMemoryTracker()->allocatedPersistent, allocatedPersistentBefore + 123 );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMemoryTracker()->allocatedRequestScoped, 0 );

    reconfigureMemoryTracker( getGlobalMemoryTracker(), memoryTrackingLevel_off, ELASTICAPM_MEMORY_TRACKING_DEFAULT_ABORT_ON_MEMORY_LEAK );
    ELASTICAPM_CMOCKA_ASSERT( ! isMemoryTrackingEnabled( getGlobalMemoryTracker() ) );

    resultCode = resultSuccess;

    finally:
    ELASTICAPM_PEFREE_STRING_AND_SET_TO_NULL( evaluateAllocationSize( 123, &isCalled ), dummyStr );
    ELASTICAPM_CMOCKA_ASSERT( ! isCalled );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMemoryTracker()->allocatedPersistent, allocatedPersistentBefore + 123 );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMemoryTracker()->allocatedRequestScoped, 0 );

    ELASTICAPM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( resultCode );
    return;

    failure:
    goto finally;
}

int run_MemoryTracker_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTICAPM_CMOCKA_UNIT_TEST( when_enabled_size_arg_to_free_should_be_evaluated_only_once ),
        ELASTICAPM_CMOCKA_UNIT_TEST( when_disabled_size_arg_to_free_should_not_be_evaluated ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}

#endif // #if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )
