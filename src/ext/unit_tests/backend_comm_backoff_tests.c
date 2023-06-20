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

#include "backend_comm_backoff.h"
#include "cmocka_wrapped_for_unit_tests.h"
#include "unit_test_util.h"
#include "mock_assert.h"

static
void test_backendCommBackoff_convertRandomUIntToJitter( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 1, backendCommBackoff_convertRandomUIntToJitter( /* randomVal */ RAND_MAX, /* jitterHalfRange */ 1 ) );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( -1, backendCommBackoff_convertRandomUIntToJitter( /* randomVal */ 0, /* jitterHalfRange */ 1 ) );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 123, backendCommBackoff_convertRandomUIntToJitter( /* randomVal */ RAND_MAX, /* jitterHalfRange */ 123 ) );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( -123, backendCommBackoff_convertRandomUIntToJitter( /* randomVal */ 0, /* jitterHalfRange */ 123 ) );

    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, backendCommBackoff_convertRandomUIntToJitter( /* randomVal */ RAND_MAX / 2, /* jitterHalfRange */ 1 ) );

//    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 100, backendCommBackoff_convertRandomUIntToJitter( /* randomVal */ RAND_MAX / 10, /* jitterHalfRange */ 1000 ) );
//    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( -900, backendCommBackoff_convertRandomUIntToJitter( /* randomVal */ RAND_MAX / 10, /* jitterHalfRange */ 1000 ) );

}

int run_backend_comm_backoff_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_backendCommBackoff_convertRandomUIntToJitter ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
