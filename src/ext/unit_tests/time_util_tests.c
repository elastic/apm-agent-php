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

int run_time_util_tests( int argc, const char* argv[] )
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_calcEndTimeVal ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_calcTimeValDiff ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
