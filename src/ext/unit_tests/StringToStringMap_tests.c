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

#include "cmocka_wrapped_for_unit_tests.h"
#include "unit_test_util.h"
#include "StringToStringMap.h"


enum { testMapStringBufferSize = 100 };

static
void assertTestMapEntry( const StringToStringMap* map, String key, String expectedValue )
{
    String actualValue;
    const bool exists = getStringToStringMapEntry( map, key, &actualValue );

    ELASTIC_APM_CMOCKA_ASSERT( exists );
    if ( expectedValue == NULL )
        ELASTIC_APM_CMOCKA_ASSERT_NULL_PTR( actualValue );
    else
        assert_string_equal( actualValue, expectedValue );
}

static
void assertTestMapEntryDoesNotExist( const StringToStringMap* map, String key )
{
    String actualValue = "dummy";
    const bool exists = getStringToStringMapEntry( map, key, &actualValue );

    ELASTIC_APM_CMOCKA_ASSERT( ! exists );
    assert_string_equal( actualValue, "dummy" );
}

static
void assertNumericTestMapEntry( const StringToStringMap* map, UInt numericKey, UInt numericValue )
{
    char keyBuffer[ testMapStringBufferSize ];
    ELASTIC_APM_CMOCKA_ASSERT( snprintf( keyBuffer, testMapStringBufferSize, "key_%u", numericKey ) < testMapStringBufferSize );
    char expectedValueBuffer[ testMapStringBufferSize ];
    ELASTIC_APM_CMOCKA_ASSERT( snprintf( expectedValueBuffer, testMapStringBufferSize, "value_%u", numericValue ) < testMapStringBufferSize );

    assertTestMapEntry( map, keyBuffer, expectedValueBuffer );
}

static
void assertNumericTestMapEntryValueIsNull( const StringToStringMap* map, UInt numericKey )
{
    char keyBuffer[ testMapStringBufferSize ];
    ELASTIC_APM_CMOCKA_ASSERT( snprintf( keyBuffer, testMapStringBufferSize, "key_%d", numericKey ) < testMapStringBufferSize );

    assertTestMapEntry( map, keyBuffer, NULL );
}

static
void assertNumericTestMapEntryDoesNotExist( const StringToStringMap* map, UInt numericKey )
{
    char keyBuffer[ testMapStringBufferSize ];
    ELASTIC_APM_CMOCKA_ASSERT( snprintf( keyBuffer, testMapStringBufferSize, "key_%d", numericKey ) < testMapStringBufferSize );

    assertTestMapEntryDoesNotExist( map, keyBuffer );
}

static
void setNumericTestMapEntry( StringToStringMap* map, UInt numericKey, UInt numericValue )
{
    char keyBuffer[ testMapStringBufferSize ];
    ELASTIC_APM_CMOCKA_ASSERT( snprintf( keyBuffer, testMapStringBufferSize, "key_%d", numericKey ) < testMapStringBufferSize );
    char valueBuffer[ testMapStringBufferSize ];
    ELASTIC_APM_CMOCKA_ASSERT( snprintf( valueBuffer, testMapStringBufferSize, "value_%d", numericValue ) < testMapStringBufferSize );

    setStringToStringMapEntry( map, keyBuffer, valueBuffer );

    assertNumericTestMapEntry( map, numericKey, numericValue );
}

static
void setNumericTestMapEntryValueToNull( StringToStringMap* map, UInt numericKey )
{
    char keyBuffer[ testMapStringBufferSize ];
    ELASTIC_APM_CMOCKA_ASSERT( snprintf( keyBuffer, testMapStringBufferSize, "key_%d", numericKey ) < testMapStringBufferSize );

    setStringToStringMapEntry( map, keyBuffer, NULL );

    assertNumericTestMapEntryValueIsNull( map, numericKey );
}

static
void deleteNumericTestMapEntry( StringToStringMap* map, UInt numericKey )
{
    char keyBuffer[ testMapStringBufferSize ];
    ELASTIC_APM_CMOCKA_ASSERT( snprintf( keyBuffer, testMapStringBufferSize, "key_%d", numericKey ) < testMapStringBufferSize );

    deleteStringToStringMapEntry( map, keyBuffer );
}

static
void various_operations( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    StringToStringMap* map = newStringToStringMap();

    ELASTIC_APM_FOR_EACH_INDEX_EX( UInt, i, 1000 )setNumericTestMapEntry( map, i, i * 2 );

    ELASTIC_APM_FOR_EACH_INDEX_EX( UInt, i, 1000 )
        if ( ( i % 3 ) == 0 )
            setNumericTestMapEntry( map, i, i * 3 );

    ELASTIC_APM_FOR_EACH_INDEX_EX( UInt, i, 1000 )
        if ( ( i % 7 ) == 0 )
            setNumericTestMapEntryValueToNull( map, i );

    ELASTIC_APM_FOR_EACH_INDEX_EX( UInt, i, 1000 )
        if ( ( i % 5 ) == 0 )
            deleteNumericTestMapEntry( map, i );

    ELASTIC_APM_FOR_EACH_INDEX_EX( UInt, i, 1000 )
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
    ELASTIC_APM_UNUSED( testFixtureState );

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
        ELASTIC_APM_CMOCKA_UNIT_TEST( various_operations ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( empty_string_key ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
