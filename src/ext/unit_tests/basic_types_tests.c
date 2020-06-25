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
    ELASTIC_APM_UNUSED( testFixtureState );

    ELASTIC_APM_STATIC_ASSERT( sizeof( Byte ) == 1 );
    ELASTIC_APM_STATIC_ASSERT( sizeof( Int8 ) == 1 );
    ELASTIC_APM_STATIC_ASSERT( sizeof( UInt8 ) == 1 );
    ELASTIC_APM_STATIC_ASSERT( sizeof( UInt ) == sizeof( int ) );
    ELASTIC_APM_STATIC_ASSERT( sizeof( Int16 ) == 2 );
    ELASTIC_APM_STATIC_ASSERT( sizeof( UInt16 ) == 2 );
    ELASTIC_APM_STATIC_ASSERT( sizeof( Int32 ) == 4 );
    ELASTIC_APM_STATIC_ASSERT( sizeof( UInt32 ) == 4 );
    ELASTIC_APM_STATIC_ASSERT( sizeof( Int64 ) == 8 );
    ELASTIC_APM_STATIC_ASSERT( sizeof( UInt64 ) == 8 );
}

static
void StringView_nonzero_size_cannot_have_NULL_begin( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    resetProductionCodeAssertFailedCount();
    setProductionCodeAssertFailed( productionCodeAssertFailedCountingMock );

    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( getProductionCodeAssertFailedCount(), 0 );
    StringView strView = makeStringView( NULL, 1 );
    ELASTIC_APM_CMOCKA_ASSERT( getProductionCodeAssertFailedCount() > 0 );
    assert_ptr_equal( strView.begin, NULL );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( strView.length, 1 );
}

static
void StringView_zero_size_can_have_NULL_begin( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    StringView strView = makeStringView( NULL, 0 );
    assert_ptr_equal( strView.begin, NULL );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( strView.length, 0 );
}

static
void StringView_from_literal( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    {
        const char literal[] = "";
        StringView strView = ELASTIC_APM_STRING_LITERAL_TO_VIEW( literal );
        assert_ptr_equal( strView.begin, literal );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( strView.length, 0 );
    }
    {
        const char literal[] = "A";
        StringView strView = ELASTIC_APM_STRING_LITERAL_TO_VIEW( literal );
        assert_ptr_equal( strView.begin, literal );
        assert_string_equal( strView.begin, "A" );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( strView.length, 1 );
    }
    {
        const char literal[] = "ABC";
        StringView strView = ELASTIC_APM_STRING_LITERAL_TO_VIEW( literal );
        assert_ptr_equal( strView.begin, literal );
        assert_string_equal( strView.begin, "ABC" );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( strView.length, 3 );
    }
}

int run_basic_types_tests_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( basic_types_sizeof ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( StringView_nonzero_size_cannot_have_NULL_begin ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( StringView_zero_size_can_have_NULL_begin ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( StringView_from_literal ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
