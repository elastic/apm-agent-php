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
#include "basic_macros.h"
#include "elastic_apm_assert.h"

static
void variadic_args_count( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_PP_VARIADIC_ARGS_COUNT(), 0 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_PP_VARIADIC_ARGS_COUNT( a ), 1 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_PP_VARIADIC_ARGS_COUNT( a, bb ), 2 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_PP_VARIADIC_ARGS_COUNT( a, bb, ccc ), 3 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_PP_VARIADIC_ARGS_COUNT( 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 ), 10 );
}

static
void if_va_args_empty_else( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE( 1, 2 ), 1 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE( 1, 2, a ), 2 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE( 1, 2, a, b, c ), 2 );
}

static
void printf_format_args( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    char buffer[ 100 ];

    snprintf( buffer, ELASTIC_APM_STATIC_ARRAY_SIZE( buffer ), ELASTIC_APM_PRINTF_FORMAT_ARGS() );
    ELASTIC_APM_ASSERT( strcmp( buffer, "" ) == 0, "buffer: %s", buffer );

    snprintf( buffer, ELASTIC_APM_STATIC_ARRAY_SIZE( buffer ), ELASTIC_APM_PRINTF_FORMAT_ARGS( "%s", "test string" ) );
    ELASTIC_APM_ASSERT( strcmp( buffer, "test string" ) == 0, "buffer: %s", buffer );

    snprintf( buffer, ELASTIC_APM_STATIC_ARRAY_SIZE( buffer ), ELASTIC_APM_PRINTF_FORMAT_ARGS( "%s and %d", "test string", -123 ) );
    ELASTIC_APM_ASSERT( strcmp( buffer, "test string and -123" ) == 0, "buffer: %s", buffer );

    snprintf(
            buffer,
            ELASTIC_APM_STATIC_ARRAY_SIZE( buffer ),
            ELASTIC_APM_PRINTF_FORMAT_ARGS(
                    "%d %d %d %d %s %d %s %d %s",
                    -1, 22, -333, 4444, "-55555", 666666, "7777777", 8, "-999999999" ) );
    ELASTIC_APM_ASSERT( strcmp( buffer, "-1 22 -333 4444 -55555 666666 7777777 8 -999999999" ) == 0, "buffer: %s", buffer );
}

static
void build_php_version_id( void** testFixtureState )
{
    ELASTIC_APM_STATIC_ASSERT( ELASTIC_APM_BUILD_PHP_VERSION_ID( 1, 2, 3 ) == 10203 );
    ELASTIC_APM_STATIC_ASSERT( ELASTIC_APM_BUILD_PHP_VERSION_ID( 8, 1, 2 ) == 80102 );
    ELASTIC_APM_ASSERT_EQ_UINT64( ELASTIC_APM_BUILD_PHP_VERSION_ID( 1, 2, 3 ), 10203 );
    ELASTIC_APM_ASSERT_EQ_UINT64( ELASTIC_APM_BUILD_PHP_VERSION_ID( 8, 1, 2 ), 80102 );
}

int run_basic_macros_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( variadic_args_count ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( if_va_args_empty_else ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( printf_format_args ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( build_php_version_id ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
