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

#include "DynamicArray.h"
#include "cmocka_wrapped_for_unit_tests.h"
#include "unit_test_util.h"
#include "mock_assert.h"
#include "elastic_apm_alloc.h"

static
void zero_capacity_DynamicArray( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    DynamicArray dynArrOnStack = ELASTIC_APM_MAKE_DYNAMIC_ARRAY( StringView );
    DynamicArray* dynArr = &dynArrOnStack;
    
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_GET_DYNAMIC_ARRAY_CAPACITY( StringView, dynArr ), 0 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_GET_DYNAMIC_ARRAY_SIZE( StringView, dynArr ), 0 );
    assert_ptr_equal( dynArr->elements, NULL );
    ELASTIC_APM_DESTRUCT_DYNAMIC_ARRAY( StringView, dynArr );

    #if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )
    setProductionCodeAssertFailed( productionCodeAssertFailedCountingMock );
    resetProductionCodeAssertFailedCount();
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( getProductionCodeAssertFailedCount(), 0 );
    ELASTIC_APM_ASSERT_VALID_DYNAMIC_ARRAY( StringView, dynArr );
    ELASTIC_APM_CMOCKA_ASSERT( getProductionCodeAssertFailedCount() > 0 );
    #endif
}

struct NumberAndString
{
    UInt number;
    String numberAsString;
};
typedef struct NumberAndString NumberAndString;

static constexpr size_t numberAsStringBufferSize = 100;

static
void addNumberAndStringDynamicArrayBack( DynamicArray* dynArr, UInt number )
{
    ResultCode resultCode;
    char* numberAsString = NULL;
    NumberAndString nas;

    ELASTIC_APM_EMALLOC_STRING_IF_FAILED_GOTO( numberAsStringBufferSize, numberAsString );
    ELASTIC_APM_CMOCKA_ASSERT(
            snprintf(
                    numberAsString,
                    numberAsStringBufferSize,
                    "%u",
                    number ) < numberAsStringBufferSize );

    nas = { .number = number, .numberAsString = numberAsString };

    ELASTIC_APM_ADD_TO_DYNAMIC_ARRAY_BACK_IF_FAILED_GOTO(
            NumberAndString,
            dynArr,
            &nas);

    const NumberAndString* addedNumberAndString;
    ELASTIC_APM_GET_DYNAMIC_ARRAY_ELEMENT_AT( NumberAndString, dynArr, ELASTIC_APM_GET_DYNAMIC_ARRAY_SIZE( NumberAndString, dynArr ) - 1, addedNumberAndString );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( addedNumberAndString->number, number );
    assert_ptr_equal( addedNumberAndString->numberAsString, numberAsString );

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( resultCode );
    return;

    failure:
    goto finally;
}

static
void assertValidNumberAndString( const NumberAndString* numberAndString )
{
    char expectedNumberAsStringBuffer[ numberAsStringBufferSize ];
    ELASTIC_APM_CMOCKA_ASSERT(
            snprintf(
                    expectedNumberAsStringBuffer,
                    numberAsStringBufferSize,
                    "%u",
                    numberAndString->number ) < numberAsStringBufferSize );

    assert_string_equal( numberAndString->numberAsString, expectedNumberAsStringBuffer );
}

static
void various_operations( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ResultCode resultCode;
    DynamicArray dynArrOnStack = ELASTIC_APM_MAKE_DYNAMIC_ARRAY( NumberAndString );
    DynamicArray* dynArr = &dynArrOnStack;

    ELASTIC_APM_FOR_EACH_INDEX_EX( UInt, i, 1000 )
        addNumberAndStringDynamicArrayBack( dynArr, i );

    UInt expectedNumber = 0;
    ELASTIC_APM_FOR_EACH_DYNAMIC_ARRAY_ELEMENT( NumberAndString, dynArrElementPtr, dynArr )
    {
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( dynArrElementPtr->number, expectedNumber );
        assertValidNumberAndString( dynArrElementPtr );
        ++expectedNumber;
    }
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( expectedNumber, 1000 );

    // Remove the first element
    ELASTIC_APM_REMOVE_DYNAMIC_ARRAY_ELEMENT_AT( NumberAndString, dynArr, 0 );
    expectedNumber = 1;
    ELASTIC_APM_FOR_EACH_DYNAMIC_ARRAY_ELEMENT( NumberAndString, dynArrElementPtr, dynArr )
    {
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( dynArrElementPtr->number, expectedNumber );
        assertValidNumberAndString( dynArrElementPtr );
        ++expectedNumber;
    }
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( expectedNumber, 1000);

    // Remove the last element
    ELASTIC_APM_REMOVE_DYNAMIC_ARRAY_ELEMENT_AT( NumberAndString, dynArr, ELASTIC_APM_GET_DYNAMIC_ARRAY_SIZE( NumberAndString, dynArr ) - 1 );
    expectedNumber = 1;
    ELASTIC_APM_FOR_EACH_DYNAMIC_ARRAY_ELEMENT( NumberAndString, dynArrElementPtr, dynArr )
    {
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( dynArrElementPtr->number, expectedNumber );
        assertValidNumberAndString( dynArrElementPtr );
        ++expectedNumber;
    }
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( expectedNumber, 999 );

    // Remove elements with odd numbers
    for ( size_t index = 0; index < ELASTIC_APM_GET_DYNAMIC_ARRAY_SIZE( NumberAndString, dynArr ); ++index )
    {
        const NumberAndString* dynArrElementPtr;
        ELASTIC_APM_GET_DYNAMIC_ARRAY_ELEMENT_AT( NumberAndString, dynArr, index, dynArrElementPtr );
        if ( dynArrElementPtr->number % 2 == 0 ) continue;
        ELASTIC_APM_REMOVE_DYNAMIC_ARRAY_ELEMENT_AT( NumberAndString, dynArr, index );
    }
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_GET_DYNAMIC_ARRAY_SIZE( NumberAndString, dynArr ), 499 );
    for ( size_t index = 0; index < ELASTIC_APM_GET_DYNAMIC_ARRAY_SIZE( NumberAndString, dynArr ); ++index )
    {
        const NumberAndString* dynArrElementPtr;
        ELASTIC_APM_GET_DYNAMIC_ARRAY_ELEMENT_AT( NumberAndString, dynArr, index, dynArrElementPtr );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( dynArrElementPtr->number, /* expectedNumber */ index * 2 + 2 );
        assertValidNumberAndString( dynArrElementPtr );
    }

    // Reduce capacity to 500
    ELASTIC_APM_CMOCKA_ASSERT( ELASTIC_APM_GET_DYNAMIC_ARRAY_CAPACITY( NumberAndString, dynArr ) > 500 );
    ELASTIC_APM_CHANGE_DYNAMIC_ARRAY_CAPACITY_IF_FAILED_GOTO( NumberAndString, dynArr, 500 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_GET_DYNAMIC_ARRAY_CAPACITY( NumberAndString, dynArr ), 500 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_GET_DYNAMIC_ARRAY_SIZE( NumberAndString, dynArr ), 499 );
    for ( size_t index = 0; index < ELASTIC_APM_GET_DYNAMIC_ARRAY_SIZE( NumberAndString, dynArr ); ++index )
    {
        const NumberAndString* dynArrElementPtr;
        ELASTIC_APM_GET_DYNAMIC_ARRAY_ELEMENT_AT( NumberAndString, dynArr, index, dynArrElementPtr );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( dynArrElementPtr->number, /* expectedNumber */ index * 2 + 2 );
        assertValidNumberAndString( dynArrElementPtr );
    }

    // Reduce capacity to 499
    ELASTIC_APM_CHANGE_DYNAMIC_ARRAY_CAPACITY_IF_FAILED_GOTO( NumberAndString, dynArr, 499 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_GET_DYNAMIC_ARRAY_CAPACITY( NumberAndString, dynArr ), 499 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_GET_DYNAMIC_ARRAY_SIZE( NumberAndString, dynArr ), 499 );
    for ( size_t index = 0; index < ELASTIC_APM_GET_DYNAMIC_ARRAY_SIZE( NumberAndString, dynArr ); ++index )
    {
        const NumberAndString* dynArrElementPtr;
        ELASTIC_APM_GET_DYNAMIC_ARRAY_ELEMENT_AT( NumberAndString, dynArr, index, dynArrElementPtr );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( dynArrElementPtr->number, /* expectedNumber */ index * 2 + 2 );
        assertValidNumberAndString( dynArrElementPtr );
    }

    finally:
    ELASTIC_APM_DESTRUCT_DYNAMIC_ARRAY( NumberAndString, dynArr );
    ELASTIC_APM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( resultCode );
    return;

    failure:
    goto finally;
}

int run_DynamicArray_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( zero_capacity_DynamicArray ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( various_operations ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
