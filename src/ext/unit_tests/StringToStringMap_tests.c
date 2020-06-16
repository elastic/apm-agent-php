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
#include "StringToStringMap.h"


enum { testMapStringBufferSize = 100 };

static
void assertTestMapEntry( const StringToStringMap* map, String key, String expectedValue )
{
    String actualValue;
    const bool exists = getStringToStringMapEntry( map, key, &actualValue );

    ELASTICAPM_CMOCKA_ASSERT( exists );
    if ( expectedValue == NULL )
        ELASTICAPM_CMOCKA_ASSERT_NULL_PTR( actualValue );
    else
        assert_string_equal( actualValue, expectedValue );
}

static
void assertTestMapEntryDoesNotExist( const StringToStringMap* map, String key )
{
    String actualValue = "dummy";
    const bool exists = getStringToStringMapEntry( map, key, &actualValue );

    ELASTICAPM_CMOCKA_ASSERT( ! exists );
    assert_string_equal( actualValue, "dummy" );
}

static
void assertNumericTestMapEntry( const StringToStringMap* map, UInt numericKey, UInt numericValue )
{
    char keyBuffer[ testMapStringBufferSize ];
    ELASTICAPM_CMOCKA_ASSERT( snprintf( keyBuffer, testMapStringBufferSize, "key_%u", numericKey ) < testMapStringBufferSize );
    char expectedValueBuffer[ testMapStringBufferSize ];
    ELASTICAPM_CMOCKA_ASSERT( snprintf( expectedValueBuffer, testMapStringBufferSize, "value_%u", numericValue ) < testMapStringBufferSize );

    assertTestMapEntry( map, keyBuffer, expectedValueBuffer );
}

static
void assertNumericTestMapEntryValueIsNull( const StringToStringMap* map, UInt numericKey )
{
    char keyBuffer[ testMapStringBufferSize ];
    ELASTICAPM_CMOCKA_ASSERT( snprintf( keyBuffer, testMapStringBufferSize, "key_%d", numericKey ) < testMapStringBufferSize );

    assertTestMapEntry( map, keyBuffer, NULL );
}

static
void assertNumericTestMapEntryDoesNotExist( const StringToStringMap* map, UInt numericKey )
{
    char keyBuffer[ testMapStringBufferSize ];
    ELASTICAPM_CMOCKA_ASSERT( snprintf( keyBuffer, testMapStringBufferSize, "key_%d", numericKey ) < testMapStringBufferSize );

    assertTestMapEntryDoesNotExist( map, keyBuffer );
}

static
void setNumericTestMapEntry( StringToStringMap* map, UInt numericKey, UInt numericValue )
{
    char keyBuffer[ testMapStringBufferSize ];
    ELASTICAPM_CMOCKA_ASSERT( snprintf( keyBuffer, testMapStringBufferSize, "key_%d", numericKey ) < testMapStringBufferSize );
    char valueBuffer[ testMapStringBufferSize ];
    ELASTICAPM_CMOCKA_ASSERT( snprintf( valueBuffer, testMapStringBufferSize, "value_%d", numericValue ) < testMapStringBufferSize );

    setStringToStringMapEntry( map, keyBuffer, valueBuffer );

    assertNumericTestMapEntry( map, numericKey, numericValue );
}

static
void setNumericTestMapEntryValueToNull( StringToStringMap* map, UInt numericKey )
{
    char keyBuffer[ testMapStringBufferSize ];
    ELASTICAPM_CMOCKA_ASSERT( snprintf( keyBuffer, testMapStringBufferSize, "key_%d", numericKey ) < testMapStringBufferSize );

    setStringToStringMapEntry( map, keyBuffer, NULL );

    assertNumericTestMapEntryValueIsNull( map, numericKey );
}

static
void deleteNumericTestMapEntry( StringToStringMap* map, UInt numericKey )
{
    char keyBuffer[ testMapStringBufferSize ];
    ELASTICAPM_CMOCKA_ASSERT( snprintf( keyBuffer, testMapStringBufferSize, "key_%d", numericKey ) < testMapStringBufferSize );

    deleteStringToStringMapEntry( map, keyBuffer );
}

static
void various_operations( void** testFixtureState )
{
    ELASTICAPM_UNUSED( testFixtureState );

    StringToStringMap* map = newStringToStringMap();

    ELASTICAPM_FOR_EACH_INDEX_EX( UInt, i, 1000 )setNumericTestMapEntry( map, i, i * 2 );

    ELASTICAPM_FOR_EACH_INDEX_EX( UInt, i, 1000 )
        if ( ( i % 3 ) == 0 )
            setNumericTestMapEntry( map, i, i * 3 );

    ELASTICAPM_FOR_EACH_INDEX_EX( UInt, i, 1000 )
        if ( ( i % 7 ) == 0 )
            setNumericTestMapEntryValueToNull( map, i );

    ELASTICAPM_FOR_EACH_INDEX_EX( UInt, i, 1000 )
        if ( ( i % 5 ) == 0 )
            deleteNumericTestMapEntry( map, i );

    ELASTICAPM_FOR_EACH_INDEX_EX( UInt, i, 1000 )
    {
        if ( ( i % 5 ) == 0 )
        {
            assertNumericTestMapEntryDoesNotExist( map, i );
            continue;
        }

        if ( ( i % 7 ) == 0 )
        {
            assertNumericTestMapEntryValueIsNull( map, i );
            continue;
        }

        if ( ( i % 3 ) == 0 )
        {
            assertNumericTestMapEntry( map, i, i * 3 );
            continue;
        }

        assertNumericTestMapEntry( map, i, i * 2 );
    }

    deleteStringToStringMapAndSetToNull( &map );
}

static
void empty_string_key( void** testFixtureState )
{
    ELASTICAPM_UNUSED( testFixtureState );

    StringToStringMap* map = newStringToStringMap();

    assertTestMapEntryDoesNotExist( map, "" );

    setStringToStringMapEntry( map, "", "" );
    assertTestMapEntry( map, "", "" );

    setStringToStringMapEntry( map, "", "some value" );
    assertTestMapEntry( map, "", "some value" );

    setStringToStringMapEntry( map, "", NULL );
    assertTestMapEntry( map, "", NULL );

    deleteStringToStringMapEntry( map, "" );
    assertTestMapEntryDoesNotExist( map, "" );

    deleteStringToStringMapAndSetToNull( &map );
}

int run_StringToStringMap_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTICAPM_CMOCKA_UNIT_TEST( various_operations ),
        ELASTICAPM_CMOCKA_UNIT_TEST( empty_string_key ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
