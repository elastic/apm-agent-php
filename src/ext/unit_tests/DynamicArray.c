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
#include "elastic_apm_alloc.h"
#include "elastic_apm_assert.h"
#include "basic_macros.h"
#include "unit_test_util.h"

void assertValidDynamicArray( const DynamicArray* dynArr, size_t elementTypeSize )
{
    ELASTIC_APM_ASSERT_VALID_PTR( dynArr );
    ELASTIC_APM_ASSERT( elementTypeSize != 0, "" );
    ELASTIC_APM_ASSERT( ( dynArr->capacity == 0 ) == ( dynArr->elements == NULL )
            , "dynArr->capacity: %"PRIu64". dynArr->elements: %p", (UInt64)dynArr->capacity, dynArr->elements );
    ELASTIC_APM_ASSERT_LE_UINT64( dynArr->size, dynArr->capacity );
}

#define ELASTIC_APM_ASSERT_VALID_DYNAMIC_ARRAY_ELEMENT_TYPE_SIZE( dynArr, elementTypeSize ) \
    ELASTIC_APM_ASSERT_VALID_OBJ( assertValidDynamicArray( (dynArr), (elementTypeSize) ) )


#if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )
static
void poisonDynamicArray( DynamicArray* dynArr )
{
    ELASTIC_APM_ASSERT_PTR_IS_NULL( dynArr->elements );
    dynArr->capacity = 0xBADC0FFE;
    dynArr->size = 0xDEADBEEF;
}
#define ELASTIC_APM_POISON_VECTOR( dynArr ) poisonDynamicArray( dynArr )
#else
#define ELASTIC_APM_POISON_VECTOR( dynArr ) ELASTIC_APM_NOOP_STATEMENT
#endif

static
void freeElementsArrayAndSetToNull( size_t elementTypeSize, size_t capacity, void** pElementsArray )
{
    ELASTIC_APM_PHP_FREE_AND_SET_TO_NULL( void, elementTypeSize * capacity, /* isPersistent */ true, *pElementsArray );
}

static
ResultCode allocElementsArray( size_t elementTypeSize, size_t capacity, void** pElementsArray )
{
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( pElementsArray );

    ResultCode resultCode;
    void* elementsArray = NULL;

    ELASTIC_APM_PHP_ALLOC_IF_FAILED_GOTO_EX(
        void,
        /* isString */ false,
        elementTypeSize * capacity,
        /* isPersistent */ true,
        elementsArray );

    resultCode = resultSuccess;
    *pElementsArray = elementsArray;

    finally:
    return resultCode;

    failure:
    freeElementsArrayAndSetToNull( elementTypeSize, capacity, &elementsArray );
    goto finally;
}

void destructDynamicArray( DynamicArray* dynArr, size_t elementTypeSize )
{
    ELASTIC_APM_ASSERT_VALID_DYNAMIC_ARRAY_ELEMENT_TYPE_SIZE( dynArr, elementTypeSize );
    
    freeElementsArrayAndSetToNull( elementTypeSize, dynArr->capacity, &dynArr->elements );
    ELASTIC_APM_POISON_VECTOR( dynArr );
}

static const size_t defaultInitialNonZeroCapacity = 10;

ResultCode addToDynamicArrayBack( DynamicArray* dynArr, void* elementToAdd, size_t elementTypeSize )
{
    ELASTIC_APM_ASSERT_VALID_DYNAMIC_ARRAY_ELEMENT_TYPE_SIZE( dynArr, elementTypeSize );

    ResultCode resultCode;

    if ( dynArr->capacity == dynArr->size )
        ELASTIC_APM_CALL_IF_FAILED_GOTO(
                changeDynamicArrayCapacity(
                        dynArr,
                        ( dynArr->capacity == 0 )
                        ? defaultInitialNonZeroCapacity
                        : dynArr->capacity * 2,
                        elementTypeSize ) );

    memcpy( ((Byte*)(dynArr->elements)) + elementTypeSize * dynArr->size, elementToAdd, elementTypeSize );
    ++dynArr->size;

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_ASSERT_VALID_DYNAMIC_ARRAY_ELEMENT_TYPE_SIZE( dynArr, elementTypeSize );
    return resultCode;

    failure:
    goto finally;
}

size_t getDynamicArraySize( const DynamicArray* dynArr, size_t elementTypeSize )
{
    ELASTIC_APM_ASSERT_VALID_DYNAMIC_ARRAY_ELEMENT_TYPE_SIZE( dynArr, elementTypeSize );
    return dynArr->size;
}

const void* getDynamicArrayElementAt( const DynamicArray* dynArr, size_t index, size_t elementTypeSize )
{
    ELASTIC_APM_ASSERT_VALID_DYNAMIC_ARRAY_ELEMENT_TYPE_SIZE( dynArr, elementTypeSize );
    ELASTIC_APM_ASSERT_LT_UINT64( index, dynArr->size );
    return (const Byte*)( dynArr->elements ) + ( index * elementTypeSize );
}

void removeDynamicArrayElementAt( DynamicArray* dynArr, size_t index, size_t elementTypeSize )
{
    ELASTIC_APM_ASSERT_VALID_DYNAMIC_ARRAY_ELEMENT_TYPE_SIZE( dynArr, elementTypeSize );
    ELASTIC_APM_ASSERT_LT_UINT64( index, dynArr->size );

    if ( index != ( dynArr->size - 1 ) )
    {
        void* removedElement = (void*)getDynamicArrayElementAt( dynArr, index, elementTypeSize );
        void* elementAfterRemoved = (void*)getDynamicArrayElementAt( dynArr, index + 1, elementTypeSize );
        memmove( removedElement, elementAfterRemoved, ( dynArr->size - ( index + 1 ) ) * elementTypeSize );
    }

    --dynArr->size;
    ELASTIC_APM_ASSERT_VALID_DYNAMIC_ARRAY_ELEMENT_TYPE_SIZE( dynArr, elementTypeSize );
}

size_t getDynamicArrayCapacity( const DynamicArray* dynArr, size_t elementTypeSize )
{
    ELASTIC_APM_ASSERT_VALID_DYNAMIC_ARRAY_ELEMENT_TYPE_SIZE( dynArr, elementTypeSize );
    return dynArr->capacity;
}

ResultCode changeDynamicArrayCapacity( DynamicArray* dynArr, size_t newCapacity, size_t elementTypeSize )
{
    ELASTIC_APM_ASSERT_VALID_DYNAMIC_ARRAY_ELEMENT_TYPE_SIZE( dynArr, elementTypeSize );
    ELASTIC_APM_ASSERT_GE_UINT64( newCapacity, dynArr->size );

    ResultCode resultCode;
    void* newElements = NULL;

    if ( dynArr->capacity == newCapacity )
    {
        resultCode = resultSuccess;
        goto finally;
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO(
            allocElementsArray( elementTypeSize, newCapacity, &newElements ) );

    resultCode = resultSuccess;

    memcpy( newElements, dynArr->elements, dynArr->size * elementTypeSize );
    freeElementsArrayAndSetToNull( elementTypeSize, dynArr->capacity, &dynArr->elements );
    dynArr->elements = newElements;
    dynArr->capacity = newCapacity;

    finally:
    ELASTIC_APM_ASSERT_VALID_DYNAMIC_ARRAY_ELEMENT_TYPE_SIZE( dynArr, elementTypeSize );
    if ( resultCode == resultSuccess ) ELASTIC_APM_ASSERT_EQ_UINT64( dynArr->capacity, newCapacity );
    return resultCode;

    failure:
    freeElementsArrayAndSetToNull( elementTypeSize, newCapacity, &newElements );
    goto finally;
}

void removeAllDynamicArrayElements( DynamicArray* dynArr, size_t elementTypeSize )
{
    ELASTIC_APM_ASSERT_VALID_DYNAMIC_ARRAY_ELEMENT_TYPE_SIZE( dynArr, elementTypeSize );
    dynArr->size = 0;
    ELASTIC_APM_ASSERT_VALID_DYNAMIC_ARRAY_ELEMENT_TYPE_SIZE( dynArr, elementTypeSize );
}