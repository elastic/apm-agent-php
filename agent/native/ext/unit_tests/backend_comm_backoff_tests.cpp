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
#include <algorithm>
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

    static constexpr int randomValStepsCount = 10;

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

    // Init random generator to get 0 jitter
    GenerateRandomUIntForTests randomGenerator = { .valueToReturn = RAND_MAX / 2 };
    BackendCommBackoff backoff = ELASTIC_APM_DEFAULT_BACKEND_COMM_BACKOFF;
    backoff.generateRandomUInt = &generateRandomUIntForTests;
    backoff.generateRandomUIntCtx = &randomGenerator;

    /**
     *  the delay after the first error is 0 seconds, then circa 1, 4, 9, 16, 25 and finally 36 seconds
     *
     * @see https://github.com/elastic/apm/blob/d8cb5607dbfffea819ab5efc9b0743044772fb23/specs/agents/transport.md#transport-errors
     */
    UInt expectedWaitTimes[] = { 0, 0, 1, 4, 9, 16, 25, 36, 36, 36 };

    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, backendCommBackoff_getTimeToWaitInSeconds( &backoff ) );
    backendCommBackoff_onSuccess( &backoff );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, backendCommBackoff_getTimeToWaitInSeconds( &backoff ) );
    ELASTIC_APM_FOR_EACH_INDEX( errorCount, ELASTIC_APM_STATIC_ARRAY_SIZE( expectedWaitTimes ) )
    {
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( expectedWaitTimes[ errorCount ], backendCommBackoff_getTimeToWaitInSeconds( &backoff ) );
        backendCommBackoff_onError( &backoff );
    }
    UInt expectedMaxWaitTime = expectedWaitTimes[ ELASTIC_APM_STATIC_ARRAY_SIZE( expectedWaitTimes ) - 1];
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( expectedMaxWaitTime, backendCommBackoff_getTimeToWaitInSeconds( &backoff ) );
    backendCommBackoff_onSuccess( &backoff );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, backendCommBackoff_getTimeToWaitInSeconds( &backoff ) );

    ELASTIC_APM_FOR_EACH_INDEX_START_END( UInt, seqLenToSimulate, 1, 10 )
    {
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, backendCommBackoff_getTimeToWaitInSeconds( &backoff ) );
        ELASTIC_APM_FOR_EACH_INDEX_EX( UInt, callIndex, seqLenToSimulate )
        {
            backendCommBackoff_onError( &backoff );
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
            UInt reconnectCount = std::min  ( callIndex, static_cast<UInt>(6) );
            UInt expectedTimeToWaitWithoutJitter = (UInt) pow( reconnectCount, 2 );
            int expectedJitter = 0;
            if ( expectedTimeToWaitWithoutJitter >= 10 )
            {
                expectedJitter = (int)( ( expectedJitterScale >= 0 ? 1 : -1 ) * floor( fabs( expectedTimeToWaitWithoutJitter * expectedJitterScale ) ) );
            }
            UInt expectedWaitTime = expectedTimeToWaitWithoutJitter + expectedJitter;
            ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( expectedWaitTime, backendCommBackoff_getTimeToWaitInSeconds( &backoff ) );
        }
        ELASTIC_APM_FOR_EACH_INDEX( callIndex, seqLenToSimulate )
        {
            backendCommBackoff_onSuccess( &backoff );
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
