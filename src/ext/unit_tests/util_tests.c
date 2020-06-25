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


static
void areStringViewsEqual_test( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ELASTIC_APM_CMOCKA_ASSERT( areStringViewsEqual( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "" ), ELASTIC_APM_STRING_LITERAL_TO_VIEW( "" ) ) );
    ELASTIC_APM_CMOCKA_ASSERT( areStringViewsEqual( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "A" ), ELASTIC_APM_STRING_LITERAL_TO_VIEW( "A" ) ) );
    ELASTIC_APM_CMOCKA_ASSERT( ! areStringViewsEqual( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "A" ), ELASTIC_APM_STRING_LITERAL_TO_VIEW( "a" ) ) );
    ELASTIC_APM_CMOCKA_ASSERT( areStringViewsEqual( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "aBc" ), ELASTIC_APM_STRING_LITERAL_TO_VIEW( "aBc" ) ) );
    ELASTIC_APM_CMOCKA_ASSERT( ! areStringViewsEqual( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "aBc" ), ELASTIC_APM_STRING_LITERAL_TO_VIEW( "AbC" ) ) );

    ELASTIC_APM_CMOCKA_ASSERT( areStringViewsEqual( makeStringView( NULL, 0 ), makeStringView( NULL, 0 ) ) );
    ELASTIC_APM_CMOCKA_ASSERT( areStringViewsEqual( makeStringView( NULL, 0 ), ELASTIC_APM_STRING_LITERAL_TO_VIEW( "" ) ) );
    ELASTIC_APM_CMOCKA_ASSERT( areStringViewsEqual( makeStringView( "x\0y", 3 ), makeStringView( "x\0yz", 3 ) ) );
    ELASTIC_APM_CMOCKA_ASSERT( ! areStringViewsEqual( makeStringView( "x\0y", 3 ), makeStringView( "x\0z", 3 ) ) );
}

static
void areStringsEqualIgnoringCase_test( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ELASTIC_APM_CMOCKA_ASSERT( areStringsEqualIgnoringCase( "", "" ) );
    ELASTIC_APM_CMOCKA_ASSERT( areStringsEqualIgnoringCase( "A", "A" ) );
    ELASTIC_APM_CMOCKA_ASSERT( areStringsEqualIgnoringCase( "A", "a" ) );
    ELASTIC_APM_CMOCKA_ASSERT( areStringsEqualIgnoringCase( "aBc", "AbC" ) );

    ELASTIC_APM_CMOCKA_ASSERT( ! areStringsEqualIgnoringCase( "a", "" ) );
    ELASTIC_APM_CMOCKA_ASSERT( ! areStringsEqualIgnoringCase( "", "a" ) );
    ELASTIC_APM_CMOCKA_ASSERT( ! areStringsEqualIgnoringCase( "aBc1", "AbC" ) );
    ELASTIC_APM_CMOCKA_ASSERT( ! areStringsEqualIgnoringCase( "aBc", "AbC1" ) );
}

static
void calcAlignedSize_test( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( calcAlignedSize( 0, 8 ), 0 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( calcAlignedSize( 1, 8 ), 8 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( calcAlignedSize( 7, 8 ), 8 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( calcAlignedSize( 8, 8 ), 8 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( calcAlignedSize( 9, 8 ), 16 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( calcAlignedSize( 15, 8 ), 16 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( calcAlignedSize( 16, 8 ), 16 );
}

int run_util_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( areStringViewsEqual_test ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( areStringsEqualIgnoringCase_test ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( calcAlignedSize_test ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
