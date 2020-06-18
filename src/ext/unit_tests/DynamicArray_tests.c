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

#include "DynamicArray.h"
#include "cmocka_wrapped_for_unit_tests.h"
#include "unit_test_util.h"
#include "mock_assert.h"
#include "elasticapm_alloc.h"

static
void zero_capacity_DynamicArray( void** testFixtureState )
{
    ELASTICAPM_UNUSED( testFixtureState );

    DynamicArray dynArrOnStack = ELASTICAPM_MAKE_DYNAMIC_ARRAY( StringView );
    DynamicArray* dynArr = &dynArrOnStack;
    
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( ELASTICAPM_GET_DYNAMIC_ARRAY_CAPACITY( StringView, dynArr ), 0 );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( ELASTICAPM_GET_DYNAMIC_ARRAY_SIZE( StringView, dynArr ), 0 );
    assert_ptr_equal( dynArr->elements, NULL );
    ELASTICAPM_DESTRUCT_DYNAMIC_ARRAY( StringView, dynArr );

    #if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )
    setProductionCodeAssertFailed( productionCodeAssertFailedCountingMock );
    resetProductionCodeAssertFailedCount();
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( getProductionCodeAssertFailedCount(), 0 );
    ELASTICAPM_ASSERT_VALID_DYNAMIC_ARRAY( StringView, dynArr );
    ELASTICAPM_CMOCKA_ASSERT( getProductionCodeAssertFailedCount() > 0 );
    #endif
}

struct NumberAndString
{
    UInt number;
    String numberAsString;
};
typedef struct NumberAndString NumberAndString;

enum { numberAsStringBufferSize = 100 };

static
void addNumberAndStringDynamicArrayBack( DynamicArray* dynArr, UInt number )
{
    ResultCode resultCode;
    char* numberAsString = NULL;

    ELASTICAPM_EMALLOC_STRING_IF_FAILED_GOTO( numberAsStringBufferSize, numberAsString );
    ELASTICAPM_CMOCKA_ASSERT(
            snprintf(
                    numberAsString,
                    numberAsStringBufferSize,
                    "%u",
                    number ) < numberAsStringBufferSize );

    ELASTICAPM_ADD_TO_DYNAMIC_ARRAY_BACK_IF_FAILED_GOTO(
            NumberAndString,
            dynArr,
            (&(NumberAndString){ .number = number, .numberAsString = numberAsString }) );

    const NumberAndString* addedNumberAndString;
    ELASTICAPM_GET_DYNAMIC_ARRAY_ELEMENT_AT( NumberAndString, dynArr, ELASTICAPM_GET_DYNAMIC_ARRAY_SIZE( NumberAndString, dynArr ) - 1, addedNumberAndString );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( addedNumberAndString->number, number );
    assert_ptr_equal( addedNumberAndString->numberAsString, numberAsString );

    resultCode = resultSuccess;

    finally:
    ELASTICAPM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( resultCode );
    return;

    failure:
    goto finally;
}

static
void assertValidNumberAndString( const NumberAndString* numberAndString )
{
    char expectedNumberAsStringBuffer[ numberAsStringBufferSize ];
    ELASTICAPM_CMOCKA_ASSERT(
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
    ELASTICAPM_UNUSED( testFixtureState );

    ResultCode resultCode;
    DynamicArray dynArrOnStack = ELASTICAPM_MAKE_DYNAMIC_ARRAY( NumberAndString );
    DynamicArray* dynArr = &dynArrOnStack;

    ELASTICAPM_FOR_EACH_INDEX_EX( UInt, i, 1000 )
        addNumberAndStringDynamicArrayBack( dynArr, i );

    UInt expectedNumber = 0;
    ELASTICAPM_FOR_EACH_DYNAMIC_ARRAY_ELEMENT( NumberAndString, dynArrElementPtr, dynArr )
    {
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( dynArrElementPtr->number, expectedNumber );
        assertValidNumberAndString( dynArrElementPtr );
        ++expectedNumber;
    }
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( expectedNumber, 1000 );

    // Remove the first element
    ELASTICAPM_REMOVE_DYNAMIC_ARRAY_ELEMENT_AT( NumberAndString, dynArr, 0 );
    expectedNumber = 1;
    ELASTICAPM_FOR_EACH_DYNAMIC_ARRAY_ELEMENT( NumberAndString, dynArrElementPtr, dynArr )
    {
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( dynArrElementPtr->number, expectedNumber );
        assertValidNumberAndString( dynArrElementPtr );
        ++expectedNumber;
    }
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( expectedNumber, 1000);

    // Remove the last element
    ELASTICAPM_REMOVE_DYNAMIC_ARRAY_ELEMENT_AT( NumberAndString, dynArr, ELASTICAPM_GET_DYNAMIC_ARRAY_SIZE( NumberAndString, dynArr ) - 1 );
    expectedNumber = 1;
    ELASTICAPM_FOR_EACH_DYNAMIC_ARRAY_ELEMENT( NumberAndString, dynArrElementPtr, dynArr )
    {
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( dynArrElementPtr->number, expectedNumber );
        assertValidNumberAndString( dynArrElementPtr );
        ++expectedNumber;
    }
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( expectedNumber, 999 );

    // Remove elements with odd numbers
    for ( size_t index = 0; index < ELASTICAPM_GET_DYNAMIC_ARRAY_SIZE( NumberAndString, dynArr ); ++index )
    {
        const NumberAndString* dynArrElementPtr;
        ELASTICAPM_GET_DYNAMIC_ARRAY_ELEMENT_AT( NumberAndString, dynArr, index, dynArrElementPtr );
        if ( dynArrElementPtr->number % 2 == 0 ) continue;
        ELASTICAPM_REMOVE_DYNAMIC_ARRAY_ELEMENT_AT( NumberAndString, dynArr, index );
    }
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( ELASTICAPM_GET_DYNAMIC_ARRAY_SIZE( NumberAndString, dynArr ), 499 );
    for ( size_t index = 0; index < ELASTICAPM_GET_DYNAMIC_ARRAY_SIZE( NumberAndString, dynArr ); ++index )
    {
        const NumberAndString* dynArrElementPtr;
        ELASTICAPM_GET_DYNAMIC_ARRAY_ELEMENT_AT( NumberAndString, dynArr, index, dynArrElementPtr );
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( dynArrElementPtr->number, /* expectedNumber */ index * 2 + 2 );
        assertValidNumberAndString( dynArrElementPtr );
    }

    // Reduce capacity to 500
    ELASTICAPM_CMOCKA_ASSERT( ELASTICAPM_GET_DYNAMIC_ARRAY_CAPACITY( NumberAndString, dynArr ) > 500 );
    ELASTICAPM_CHANGE_DYNAMIC_ARRAY_CAPACITY_IF_FAILED_GOTO( NumberAndString, dynArr, 500 );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( ELASTICAPM_GET_DYNAMIC_ARRAY_CAPACITY( NumberAndString, dynArr ), 500 );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( ELASTICAPM_GET_DYNAMIC_ARRAY_SIZE( NumberAndString, dynArr ), 499 );
    for ( size_t index = 0; index < ELASTICAPM_GET_DYNAMIC_ARRAY_SIZE( NumberAndString, dynArr ); ++index )
    {
        const NumberAndString* dynArrElementPtr;
        ELASTICAPM_GET_DYNAMIC_ARRAY_ELEMENT_AT( NumberAndString, dynArr, index, dynArrElementPtr );
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( dynArrElementPtr->number, /* expectedNumber */ index * 2 + 2 );
        assertValidNumberAndString( dynArrElementPtr );
    }

    // Reduce capacity to 499
    ELASTICAPM_CHANGE_DYNAMIC_ARRAY_CAPACITY_IF_FAILED_GOTO( NumberAndString, dynArr, 499 );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( ELASTICAPM_GET_DYNAMIC_ARRAY_CAPACITY( NumberAndString, dynArr ), 499 );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( ELASTICAPM_GET_DYNAMIC_ARRAY_SIZE( NumberAndString, dynArr ), 499 );
    for ( size_t index = 0; index < ELASTICAPM_GET_DYNAMIC_ARRAY_SIZE( NumberAndString, dynArr ); ++index )
    {
        const NumberAndString* dynArrElementPtr;
        ELASTICAPM_GET_DYNAMIC_ARRAY_ELEMENT_AT( NumberAndString, dynArr, index, dynArrElementPtr );
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( dynArrElementPtr->number, /* expectedNumber */ index * 2 + 2 );
        assertValidNumberAndString( dynArrElementPtr );
    }

    finally:
    ELASTICAPM_DESTRUCT_DYNAMIC_ARRAY( NumberAndString, dynArr );
    ELASTICAPM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( resultCode );
    return;

    failure:
    goto finally;
}

int run_DynamicArray_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTICAPM_CMOCKA_UNIT_TEST( zero_capacity_DynamicArray ),
        ELASTICAPM_CMOCKA_UNIT_TEST( various_operations ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
