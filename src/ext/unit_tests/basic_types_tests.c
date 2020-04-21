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

#include "basic_types.h"
#include "cmocka_wrapped_for_unit_tests.h"
#include "unit_test_util.h"
#include "mock_assert.h"

static
void basic_types_sizeof( void** testFixtureState )
{
    ELASTICAPM_UNUSED( testFixtureState );

    ELASTICAPM_STATIC_ASSERT( sizeof( Byte ) == 1 );
    ELASTICAPM_STATIC_ASSERT( sizeof( Int8 ) == 1 );
    ELASTICAPM_STATIC_ASSERT( sizeof( UInt8 ) == 1 );
    ELASTICAPM_STATIC_ASSERT( sizeof( UInt ) == sizeof( int ) );
    ELASTICAPM_STATIC_ASSERT( sizeof( Int16 ) == 2 );
    ELASTICAPM_STATIC_ASSERT( sizeof( UInt16 ) == 2 );
    ELASTICAPM_STATIC_ASSERT( sizeof( Int32 ) == 4 );
    ELASTICAPM_STATIC_ASSERT( sizeof( UInt32 ) == 4 );
    ELASTICAPM_STATIC_ASSERT( sizeof( Int64 ) == 8 );
    ELASTICAPM_STATIC_ASSERT( sizeof( UInt64 ) == 8 );
}

static
void StringView_nonzero_size_cannot_have_NULL_begin( void** testFixtureState )
{
    ELASTICAPM_UNUSED( testFixtureState );

    resetProductionCodeAssertFailedCount();
    setProductionCodeAssertFailed( productionCodeAssertFailedCountingMock );

    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( getProductionCodeAssertFailedCount(), 0 );
    StringView strView = makeStringView( NULL, 1 );
    ELASTICAPM_CMOCKA_ASSERT( getProductionCodeAssertFailedCount() > 0 );
    assert_ptr_equal( strView.begin, NULL );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( strView.length, 1 );
}

static
void StringView_zero_size_can_have_NULL_begin( void** testFixtureState )
{
    ELASTICAPM_UNUSED( testFixtureState );

    StringView strView = makeStringView( NULL, 0 );
    assert_ptr_equal( strView.begin, NULL );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( strView.length, 0 );
}

static
void StringView_from_literal( void** testFixtureState )
{
    ELASTICAPM_UNUSED( testFixtureState );

    {
        const char literal[] = "";
        StringView strView = ELASTICAPM_STRING_LITERAL_TO_VIEW( literal );
        assert_ptr_equal( strView.begin, literal );
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( strView.length, 0 );
    }
    {
        const char literal[] = "A";
        StringView strView = ELASTICAPM_STRING_LITERAL_TO_VIEW( literal );
        assert_ptr_equal( strView.begin, literal );
        assert_string_equal( strView.begin, "A" );
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( strView.length, 1 );
    }
    {
        const char literal[] = "ABC";
        StringView strView = ELASTICAPM_STRING_LITERAL_TO_VIEW( literal );
        assert_ptr_equal( strView.begin, literal );
        assert_string_equal( strView.begin, "ABC" );
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( strView.length, 3 );
    }
}

int run_basic_types_tests_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTICAPM_CMOCKA_UNIT_TEST( basic_types_sizeof ),
        ELASTICAPM_CMOCKA_UNIT_TEST( StringView_nonzero_size_cannot_have_NULL_begin ),
        ELASTICAPM_CMOCKA_UNIT_TEST( StringView_zero_size_can_have_NULL_begin ),
        ELASTICAPM_CMOCKA_UNIT_TEST( StringView_from_literal ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
