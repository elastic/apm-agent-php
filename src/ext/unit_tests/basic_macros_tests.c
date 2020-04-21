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
    ELASTICAPM_UNUSED( testFixtureState );

    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( ELASTICAPM_PP_VARIADIC_ARGS_COUNT(), 0 );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( ELASTICAPM_PP_VARIADIC_ARGS_COUNT( a ), 1 );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( ELASTICAPM_PP_VARIADIC_ARGS_COUNT( a, bb ), 2 );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( ELASTICAPM_PP_VARIADIC_ARGS_COUNT( a, bb, ccc ), 3 );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( ELASTICAPM_PP_VARIADIC_ARGS_COUNT( 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 ), 10 );

    ELASTICAPM_ASSERT( true, "some message", "" );
}

static
void printf_format_args( void** testFixtureState )
{
    ELASTICAPM_UNUSED( testFixtureState );

    char buffer[ 100 ];

    snprintf( buffer, ELASTICAPM_STATIC_ARRAY_SIZE( buffer ), ELASTICAPM_PRINTF_FORMAT_ARGS() );
    ELASTICAPM_ASSERT( strcmp( buffer, "" ) == 0, buffer );

    snprintf( buffer, ELASTICAPM_STATIC_ARRAY_SIZE( buffer ), ELASTICAPM_PRINTF_FORMAT_ARGS( "%s", "test string" ) );
    ELASTICAPM_ASSERT( strcmp( buffer, "test string" ) == 0, "" );

    snprintf( buffer, ELASTICAPM_STATIC_ARRAY_SIZE( buffer ), ELASTICAPM_PRINTF_FORMAT_ARGS( "%s and %d", "test string", -123 ) );
    ELASTICAPM_ASSERT( strcmp( buffer, "test string and -123" ) == 0, "" );

    snprintf(
            buffer,
            ELASTICAPM_STATIC_ARRAY_SIZE( buffer ),
            ELASTICAPM_PRINTF_FORMAT_ARGS(
                    "%d %d %d %d %s %d %s %d %s",
                    -1, 22, -333, 4444, "-55555", 666666, "7777777", 8, "-999999999" ) );
    ELASTICAPM_ASSERT( strcmp( buffer, "-1 22 -333 4444 -55555 666666 7777777 8 -999999999" ) == 0, buffer );
}

int run_basic_macros_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTICAPM_CMOCKA_UNIT_TEST( variadic_args_count ),
        ELASTICAPM_CMOCKA_UNIT_TEST( printf_format_args ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
