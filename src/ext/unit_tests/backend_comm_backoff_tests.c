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
#include <math.h>

static
void test_backendCommBackoff_convertRandomUIntToJitter_helper( double randomVal01, UInt jitterHalfRange )
{
    UInt randomVal = (UInt)( RAND_MAX * randomVal01 );
    int expectedResult = floor( jitterHalfRange * ( ( randomVal01 - 0.5 ) / 0.5 ) );
    int actualResult = backendCommBackoff_convertRandomUIntToJitter( randomVal, jitterHalfRange );
    if ( randomVal01 == 0.0 || randomVal01 == 1.0 )
    {
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( expectedResult, actualResult );
    }
    else
    {
        ELASTIC_APM_CMOCKA_ASSERT_INT_IN_RANGE( expectedResult - 1, actualResult, expectedResult + 1 );
    }
}

static
void test_backendCommBackoff_convertRandomUIntToJitter( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 1, backendCommBackoff_convertRandomUIntToJitter( /* randomVal */ RAND_MAX, /* jitterHalfRange */ 1 ) );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( -1, backendCommBackoff_convertRandomUIntToJitter( /* randomVal */ 0, /* jitterHalfRange */ 1 ) );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 123, backendCommBackoff_convertRandomUIntToJitter( /* randomVal */ RAND_MAX, /* jitterHalfRange */ 123 ) );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( -123, backendCommBackoff_convertRandomUIntToJitter( /* randomVal */ 0, /* jitterHalfRange */ 123 ) );

    enum { randomValStepsCount = 10 };

    for ( UInt jitterHalfRange = 1 ; jitterHalfRange < 100 ; jitterHalfRange *= 2 )
    {
        // +1 to include both end of [0, 1] range
        ELASTIC_APM_FOR_EACH_INDEX( randomValStep, randomValStepsCount + 1 )
        {
            test_backendCommBackoff_convertRandomUIntToJitter_helper( /* randomVal01 */ ( (double) randomValStep ) / randomValStepsCount, jitterHalfRange );
        }
    }
}

struct GenerateRandomUIntForTests
{
    UInt valueToReturn;
};
typedef struct GenerateRandomUIntForTests GenerateRandomUIntForTests;

UInt generateRandomUIntForTests( void* ctx )
{
    const GenerateRandomUIntForTests* thisObj = (const GenerateRandomUIntForTests*)ctx;
    return thisObj->valueToReturn;
}

static
void test_backendCommBackoff_getTimeToWaitInSeconds( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    GenerateRandomUIntForTests randomGenerator = { .valueToReturn = RAND_MAX / 2 };
    BackendCommBackoff backendCommBackoff;
    backendCommBackoff_init( &generateRandomUIntForTests, &randomGenerator, &backendCommBackoff );

    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, backendCommBackoff_getTimeToWaitInSeconds( &backendCommBackoff ) );
    backendCommBackoff_onError( &backendCommBackoff );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, backendCommBackoff_getTimeToWaitInSeconds( &backendCommBackoff ) );
    backendCommBackoff_onError( &backendCommBackoff );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 1, backendCommBackoff_getTimeToWaitInSeconds( &backendCommBackoff ) );
    backendCommBackoff_onError( &backendCommBackoff );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 4, backendCommBackoff_getTimeToWaitInSeconds( &backendCommBackoff ) );
    backendCommBackoff_onError( &backendCommBackoff );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 9, backendCommBackoff_getTimeToWaitInSeconds( &backendCommBackoff ) );
    backendCommBackoff_onError( &backendCommBackoff );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 16, backendCommBackoff_getTimeToWaitInSeconds( &backendCommBackoff ) );
    backendCommBackoff_onError( &backendCommBackoff );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 25, backendCommBackoff_getTimeToWaitInSeconds( &backendCommBackoff ) );
    backendCommBackoff_onError( &backendCommBackoff );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 36, backendCommBackoff_getTimeToWaitInSeconds( &backendCommBackoff ) );
    backendCommBackoff_onError( &backendCommBackoff );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 36, backendCommBackoff_getTimeToWaitInSeconds( &backendCommBackoff ) );
    backendCommBackoff_onError( &backendCommBackoff );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 36, backendCommBackoff_getTimeToWaitInSeconds( &backendCommBackoff ) );
    backendCommBackoff_onSuccess( &backendCommBackoff );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, backendCommBackoff_getTimeToWaitInSeconds( &backendCommBackoff ) );

    ELASTIC_APM_FOR_EACH_INDEX_START_END( UInt, seqLenToSimulate, 1, 10 )
    {
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, backendCommBackoff_getTimeToWaitInSeconds( &backendCommBackoff ) );
        ELASTIC_APM_FOR_EACH_INDEX_EX( UInt, callIndex, seqLenToSimulate )
        {
            backendCommBackoff_onError( &backendCommBackoff );
            double expectedJitterScale = 0;
            switch ( callIndex % 3 )
            {
                case 0:
                    randomGenerator.valueToReturn = RAND_MAX / 2;
                    expectedJitterScale = 0;
                    break;
                case 1:
                    randomGenerator.valueToReturn = 0;
                    expectedJitterScale = -0.1;
                    break;
                case 2:
                    randomGenerator.valueToReturn = RAND_MAX;
                    expectedJitterScale = 0.1;
                    break;
            }
            UInt reconnectCount = ELASTIC_APM_MIN( callIndex, 6 );
            UInt expectedTimeToWaitWithoutJitter = (UInt) pow( reconnectCount, 2 );
            int expectedJitter = 0;
            if ( expectedTimeToWaitWithoutJitter >= 10 )
            {
                expectedJitter = (int)( ( expectedJitterScale >= 0 ? 1 : -1 ) * floor( fabs( expectedTimeToWaitWithoutJitter * expectedJitterScale ) ) );
            }
            UInt expectedTimeToWait = expectedTimeToWaitWithoutJitter + expectedJitter;
            ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( expectedTimeToWait, backendCommBackoff_getTimeToWaitInSeconds( &backendCommBackoff ) );
        }
        ELASTIC_APM_FOR_EACH_INDEX( callIndex, seqLenToSimulate )
        {
            backendCommBackoff_onSuccess( &backendCommBackoff );
        }
    }
}

int run_backend_comm_backoff_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_backendCommBackoff_convertRandomUIntToJitter ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_backendCommBackoff_getTimeToWaitInSeconds ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
