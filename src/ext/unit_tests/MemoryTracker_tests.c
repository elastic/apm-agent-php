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

#include "cmocka_wrapped_for_unit_tests.h"
#include "unit_test_util.h"
#include "elastic_apm_alloc.h"

#if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )

static
size_t evaluateAllocationSize( size_t retVal, bool* isCalled )
{
    ELASTIC_APM_ASSERT_VALID_PTR( isCalled );
    ELASTIC_APM_CMOCKA_ASSERT( ! *isCalled );

    *isCalled = true;
    return retVal;
}

static
void when_enabled_size_arg_to_free_should_be_evaluated_only_once( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ResultCode resultCode;
    char* dummyStr = NULL;
    bool isCalled = false;

    ELASTIC_APM_CMOCKA_ASSERT( isMemoryTrackingEnabled( getGlobalMemoryTracker() ) );
    const UInt64 allocatedPersistentBefore = getGlobalMemoryTracker()->allocatedPersistent;
    const UInt64 allocatedRequestScopedBefore = getGlobalMemoryTracker()->allocatedRequestScoped;

    ELASTIC_APM_EMALLOC_STRING_IF_FAILED_GOTO( 123, dummyStr );
    // +1 for terminating '\0'
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMemoryTracker()->allocatedPersistent, allocatedPersistentBefore );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMemoryTracker()->allocatedRequestScoped, allocatedRequestScopedBefore + 123 );

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_EFREE_STRING_SIZE_AND_SET_TO_NULL( evaluateAllocationSize( 123, &isCalled ), dummyStr );
    ELASTIC_APM_CMOCKA_ASSERT( isCalled );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMemoryTracker()->allocatedPersistent, allocatedPersistentBefore );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMemoryTracker()->allocatedRequestScoped, allocatedRequestScopedBefore );

    ELASTIC_APM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( resultCode );
    return;

    failure:
    goto finally;
}

static
void when_disabled_size_arg_to_free_should_not_be_evaluated( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ResultCode resultCode;
    char* dummyStr = NULL;
    bool isCalled = false;

    ELASTIC_APM_CMOCKA_ASSERT( isMemoryTrackingEnabled( getGlobalMemoryTracker() ) );
    const UInt64 allocatedPersistentBefore = getGlobalMemoryTracker()->allocatedPersistent;
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMemoryTracker()->allocatedRequestScoped, 0 );

    ELASTIC_APM_PEMALLOC_STRING_IF_FAILED_GOTO( 123, dummyStr );
    // +1 for terminating '\0'
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMemoryTracker()->allocatedPersistent, allocatedPersistentBefore + 123 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMemoryTracker()->allocatedRequestScoped, 0 );

    reconfigureMemoryTracker( getGlobalMemoryTracker(), memoryTrackingLevel_off, ELASTIC_APM_MEMORY_TRACKING_DEFAULT_ABORT_ON_MEMORY_LEAK );
    ELASTIC_APM_CMOCKA_ASSERT( ! isMemoryTrackingEnabled( getGlobalMemoryTracker() ) );

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_PEFREE_STRING_SIZE_AND_SET_TO_NULL( evaluateAllocationSize( 123, &isCalled ), dummyStr );
    ELASTIC_APM_CMOCKA_ASSERT( ! isCalled );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMemoryTracker()->allocatedPersistent, allocatedPersistentBefore + 123 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMemoryTracker()->allocatedRequestScoped, 0 );

    ELASTIC_APM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( resultCode );
    return;

    failure:
    goto finally;
}

int run_MemoryTracker_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( when_enabled_size_arg_to_free_should_be_evaluated_only_once ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( when_disabled_size_arg_to_free_should_not_be_evaluated ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}

#endif // #if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )
