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

#pragma once

#include <stddef.h>
#include "ResultCode.h"

struct DynamicArray
{
    size_t capacity; // as number of elements
    size_t size; // as number of elements
    void* elements;
};
typedef struct DynamicArray DynamicArray;

void destructDynamicArray( DynamicArray* dynArr, size_t elementTypeSize );
void assertValidDynamicArray( const DynamicArray* dynArr, size_t elementTypeSize );
ResultCode addToDynamicArrayBack( DynamicArray* dynArr, void* elementToAdd, size_t elementTypeSize );
size_t getDynamicArraySize( const DynamicArray* dynArr, size_t elementTypeSize );
const void* getDynamicArrayElementAt( const DynamicArray* dynArr, size_t index, size_t elementTypeSize );
void removeDynamicArrayElementAt( DynamicArray* dynArr, size_t index, size_t elementTypeSize );
size_t getDynamicArrayCapacity( const DynamicArray* dynArr, size_t elementTypeSize );
ResultCode changeDynamicArrayCapacity( DynamicArray* dynArr, size_t newCapacity, size_t elementTypeSize );
void removeAllDynamicArrayElements( DynamicArray* dynArr, size_t elementTypeSize );

#define ELASTIC_APM_ASSERT_VALID_DYNAMIC_ARRAY( ElementType, dynArr ) \
    ELASTIC_APM_ASSERT_VALID_OBJ( assertValidDynamicArray( (dynArr), sizeof( ElementType ) ) )

#define ELASTIC_APM_FOR_EACH_DYNAMIC_ARRAY_ELEMENT( ElementType, elementPtrVar, dynArr ) \
    for ( ElementType* elementPtrVar = (ElementType*)((dynArr)->elements), * elementsEnd = ((ElementType*)((dynArr)->elements)) + (dynArr)->size ; \
            (elementPtrVar) != elementsEnd ; \
            ++(elementPtrVar) )

#define ELASTIC_APM_MAKE_DYNAMIC_ARRAY( ElementType ) \
        ((DynamicArray){ .capacity = 0, .size = 0, .elements = NULL })


#define ELASTIC_APM_DESTRUCT_DYNAMIC_ARRAY( ElementType, dynArr ) \
    destructDynamicArray( (dynArr), sizeof( ElementType ) )

#define ELASTIC_APM_ADD_TO_DYNAMIC_ARRAY_BACK_IF_FAILED_GOTO( ElementType, dynArr, elementToAdd ) \
    ELASTIC_APM_CALL_IF_FAILED_GOTO( addToDynamicArrayBack( (dynArr), (elementToAdd), sizeof( ElementType ) ) )

#define ELASTIC_APM_GET_DYNAMIC_ARRAY_SIZE( ElementType, dynArr ) \
    getDynamicArraySize( (dynArr), sizeof( ElementType ) )

#define ELASTIC_APM_GET_DYNAMIC_ARRAY_ELEMENT_AT( ElementType, dynArr, index, outPtr ) \
    do { \
        outPtr = (ElementType*)( getDynamicArrayElementAt( (dynArr), (index), sizeof( ElementType ) ) ); \
    } while( 0 )

#define ELASTIC_APM_REMOVE_DYNAMIC_ARRAY_ELEMENT_AT( ElementType, dynArr, index ) \
    removeDynamicArrayElementAt( (dynArr), (index), sizeof( ElementType ) )

#define ELASTIC_APM_GET_DYNAMIC_ARRAY_CAPACITY( ElementType, dynArr ) \
    getDynamicArrayCapacity( (dynArr), sizeof( ElementType ) )

#define ELASTIC_APM_CHANGE_DYNAMIC_ARRAY_CAPACITY_IF_FAILED_GOTO( ElementType, dynArr, newCapacity ) \
    ELASTIC_APM_CALL_IF_FAILED_GOTO( changeDynamicArrayCapacity( (dynArr), (newCapacity), sizeof( ElementType ) ) )

#define ELASTIC_APM_REMOVE_ALL_DYNAMIC_ARRAY_ELEMENTS( ElementType, dynArr ) \
    removeAllDynamicArrayElements( (dynArr), sizeof( ElementType ) )
