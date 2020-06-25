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

#include "unit_test_util.h"

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

int run_basic_macros_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( variadic_args_count ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( if_va_args_empty_else ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( printf_format_args ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
