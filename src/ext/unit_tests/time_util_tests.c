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

#include "unit_test_util.h"
#include "time_util.h"
#include <limits.h>

static const long microsecondsInSecond = 1000 * 1000;
static const long nanosecondsInMicrosecond = 1000;
static const long nanosecondsInSecond = microsecondsInSecond * nanosecondsInMicrosecond;

static
void test_calcEndTimeVal( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    {
        TimeVal beginTime = { .tv_sec = 0, .tv_usec = 0 };
        TimeVal endTime = calcEndTimeVal( beginTime, 0, 0 );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( endTime.tv_sec, 0 );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( endTime.tv_usec, 0 );
    }
    {
        TimeVal beginTime = { .tv_sec = 0, .tv_usec = 0 };
        TimeVal endTime = calcEndTimeVal( beginTime, 1, 2 * nanosecondsInMicrosecond );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( endTime.tv_sec, 1 );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( endTime.tv_usec, 2 );
    }
    {
        TimeVal beginTime = { .tv_sec = 123, .tv_usec = microsecondsInSecond - 1 };
        TimeVal endTime = calcEndTimeVal( beginTime, 456, ( microsecondsInSecond - 1 ) * nanosecondsInMicrosecond );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( endTime.tv_sec, 123 + 456 + 1 );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( endTime.tv_usec, microsecondsInSecond - 2 );
    }
    {
        TimeVal beginTime = { .tv_sec = LONG_MAX - 1, .tv_usec = microsecondsInSecond - 2 };
        TimeVal endTime = calcEndTimeVal( beginTime, 1, nanosecondsInMicrosecond );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( endTime.tv_sec, LONG_MAX );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( endTime.tv_usec, microsecondsInSecond - 1 );
    }
    {
        TimeVal beginTime = { .tv_sec = LONG_MAX - 2, .tv_usec = microsecondsInSecond - 1 };
        TimeVal endTime = calcEndTimeVal( beginTime, 1, nanosecondsInMicrosecond );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( endTime.tv_sec, LONG_MAX );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( endTime.tv_usec, 0 );
    }
    {
        TimeVal beginTime = { .tv_sec = LONG_MAX - 1, .tv_usec = microsecondsInSecond - 1 };
        TimeVal endTime = calcEndTimeVal( beginTime, 0, nanosecondsInSecond );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( endTime.tv_sec, LONG_MAX );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( endTime.tv_usec, microsecondsInSecond - 1 );
    }
}

static
void test_calcTimeValDiff( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    {
        TimeVal beginTime = { .tv_sec = 0, .tv_usec = 0 };
        TimeVal endTime = { .tv_sec = 0, .tv_usec = 0 };
        TimeVal diff = calcTimeValDiff( beginTime, endTime );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( diff.tv_sec, 0 );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( diff.tv_usec, 0 );
    }
    {
        TimeVal beginTime = { .tv_sec = 0, .tv_usec = 0 };
        TimeVal endTime = { .tv_sec = 1, .tv_usec = 2 };
        TimeVal diff = calcTimeValDiff( beginTime, endTime );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( diff.tv_sec, 1 );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( diff.tv_usec, 2 );
    }
    {
        TimeVal beginTime = { .tv_sec = 1, .tv_usec = 2 };
        TimeVal endTime = { .tv_sec = 0, .tv_usec = 0 };
        TimeVal diff = calcTimeValDiff( beginTime, endTime );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( diff.tv_sec, -1 );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( diff.tv_usec, -2 );
    }
    {
        TimeVal beginTime = { .tv_sec = 1, .tv_usec = 2 };
        TimeVal endTime = { .tv_sec = 1, .tv_usec = 0 };
        TimeVal diff = calcTimeValDiff( beginTime, endTime );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( diff.tv_sec, 0 );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( diff.tv_usec, -2 );
    }
    {
        TimeVal beginTime = { .tv_sec = 123, .tv_usec = 456 };
        TimeVal endTime = { .tv_sec = 124, .tv_usec = 421 };
        TimeVal diff = calcTimeValDiff( beginTime, endTime );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( diff.tv_sec, 0 );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( diff.tv_usec, microsecondsInSecond + 421 - 456 );
    }
}

static
void impl_test_one_durationToMilliseconds( Duration inputDuration, Int64 expectedDurationInMilliseconds )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    Int64 actualDurationInMilliseconds = durationToMilliseconds( inputDuration );
    ELASTIC_APM_CMOCKA_ASSERT_MSG(
            actualDurationInMilliseconds == expectedDurationInMilliseconds
            , "inputDuration: %s, expectedDurationInMilliseconds: %"PRId64", actualDurationInMilliseconds: %"PRId64
            , streamDuration( inputDuration, &txtOutStream ), expectedDurationInMilliseconds, actualDurationInMilliseconds );
}

static
void test_durationToMilliseconds( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    // Test zero
    ELASTIC_APM_FOR_EACH_INDEX( i, numberOfDurationUnits )
    {
        impl_test_one_durationToMilliseconds( makeDuration( 0, (DurationUnits)i ), /* expectedDurationInMilliseconds */ 0 );
    }

    Int64 factor[ numberOfDurationUnits ] =
    {
        [ durationUnits_millisecond ] = 1,
        [ durationUnits_second ] = 1000,
        [ durationUnits_minute ] = 60 * 1000,
    };
    Int64 valueInUnitsVariants[] = { 1, -1, 123, -4567890, INT8_MAX, INT8_MIN, INT16_MAX, INT16_MIN, INT32_MAX, INT32_MIN };
    ELASTIC_APM_FOR_EACH_INDEX( valueVariantIndex, ELASTIC_APM_STATIC_ARRAY_SIZE( valueInUnitsVariants ) )
    {
        Int64 valueInUnits = valueInUnitsVariants[ valueVariantIndex ];
        ELASTIC_APM_FOR_EACH_INDEX( i, numberOfDurationUnits )
        {
            Int64 expectedDurationInMilliseconds = valueInUnits * factor[ i ];
            impl_test_one_durationToMilliseconds( makeDuration( valueInUnits, (DurationUnits)i ), expectedDurationInMilliseconds );
        }
    }
}

static
void impl_test_addDelayToAbsTimeSpec( TimeSpec base, long delayInNanoseconds, TimeSpec expectedResult )
{
    TimeSpec actualResult = base;
    addDelayToAbsTimeSpec( /* in, out */ &actualResult, delayInNanoseconds );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( actualResult.tv_sec, expectedResult.tv_sec );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( actualResult.tv_nsec, expectedResult.tv_nsec );
}

static
bool time_t_max()
{
    ELASTIC_APM_STATIC_ASSERT( sizeof( time_t ) == 4 || sizeof( time_t ) == 8 );
    return sizeof( time_t ) == 4 ? INT32_MAX : INT64_MAX;
}

static
TimeSpec buildTimeSpec( time_t seconds, long nanoseconds )
{
    return (TimeSpec){ .tv_sec = seconds, .tv_nsec = nanoseconds };
}

#pragma clang diagnostic push
#pragma ide diagnostic ignored "UnusedParameter"
static
void test_addDelayToAbsTimeSpec( void** testFixtureState )
{
    impl_test_addDelayToAbsTimeSpec( buildTimeSpec( 0, 0 ), 0, buildTimeSpec( 0, 0 ) );
#pragma clang diagnostic push
#pragma clang diagnostic ignored "-Wshadow"
    long nanosecondsInSecond = ELASTIC_APM_NUMBER_OF_NANOSECONDS_IN_SECOND;
#pragma clang diagnostic pop
    impl_test_addDelayToAbsTimeSpec( buildTimeSpec( 0, nanosecondsInSecond - 1 ), 0, buildTimeSpec( 0, nanosecondsInSecond - 1 ) );
    impl_test_addDelayToAbsTimeSpec( buildTimeSpec( time_t_max(), nanosecondsInSecond - 1 ), 0, buildTimeSpec( time_t_max(), nanosecondsInSecond - 1 ) );
    impl_test_addDelayToAbsTimeSpec( buildTimeSpec( 0, nanosecondsInSecond - 1 ), 1, buildTimeSpec( 1, 0 ) );
    impl_test_addDelayToAbsTimeSpec( buildTimeSpec( time_t_max() - 1, nanosecondsInSecond - 1 ), 1, buildTimeSpec( time_t_max(), 0 ) );
    impl_test_addDelayToAbsTimeSpec( buildTimeSpec( 0, 1 ), nanosecondsInSecond - 1, buildTimeSpec( 1, 0 ) );
    impl_test_addDelayToAbsTimeSpec( buildTimeSpec( time_t_max() - 1, 1 ), nanosecondsInSecond - 1, buildTimeSpec( time_t_max(), 0 ) );

    impl_test_addDelayToAbsTimeSpec( buildTimeSpec( 0, nanosecondsInSecond - 1 ), nanosecondsInSecond - 1, buildTimeSpec( 1, nanosecondsInSecond - 2 ) );
    impl_test_addDelayToAbsTimeSpec( buildTimeSpec( time_t_max() - 1, nanosecondsInSecond - 1 ), nanosecondsInSecond - 1, buildTimeSpec( time_t_max(), nanosecondsInSecond - 2 ) );
    impl_test_addDelayToAbsTimeSpec( buildTimeSpec( 0, nanosecondsInSecond - 1 ), nanosecondsInSecond, buildTimeSpec( 1, nanosecondsInSecond - 1 ) );
    impl_test_addDelayToAbsTimeSpec( buildTimeSpec( time_t_max() - 1, nanosecondsInSecond - 1 ), nanosecondsInSecond, buildTimeSpec( time_t_max(), nanosecondsInSecond - 1 ) );
    impl_test_addDelayToAbsTimeSpec( buildTimeSpec( 0, nanosecondsInSecond/2 ), nanosecondsInSecond/2, buildTimeSpec( 1, 0 ) );
    impl_test_addDelayToAbsTimeSpec( buildTimeSpec( time_t_max() - 1, nanosecondsInSecond/2 ), nanosecondsInSecond/2, buildTimeSpec( time_t_max(), 0 ) );

    impl_test_addDelayToAbsTimeSpec( buildTimeSpec( 0, 123 ), 4*nanosecondsInSecond - 567, buildTimeSpec( 3, 123 + nanosecondsInSecond - 567 ) );
    impl_test_addDelayToAbsTimeSpec( buildTimeSpec( time_t_max() - 3, 123 ), 4*nanosecondsInSecond - 567, buildTimeSpec( time_t_max(), 123 + nanosecondsInSecond - 567 ) );
}
#pragma clang diagnostic pop

#pragma clang diagnostic push
#pragma ide diagnostic ignored "UnusedParameter"
#pragma ide diagnostic ignored "OCUnusedGlobalDeclarationInspection"
int run_time_util_tests( int argc, const char* argv[] )
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_calcEndTimeVal ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_calcTimeValDiff ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_durationToMilliseconds ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_addDelayToAbsTimeSpec ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
#pragma clang diagnostic pop
