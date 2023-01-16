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

#include <stdbool.h>
#include "basic_types.h"
#include "MemoryTracker.h"
#include "internal_checks.h"

#if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )
#   include "platform.h" // ELASTIC_APM_CAPTURE_STACK_TRACE
#endif

#ifndef ELASTIC_APM_PEMALLOC_FUNC
#   include <php.h>
#   define ELASTIC_APM_PEMALLOC_FUNC pemalloc
#   define ELASTIC_APM_PEFREE_FUNC pefree
#else
// Declare to avoid warnings
void* ELASTIC_APM_PEMALLOC_FUNC ( size_t requestedSize, bool isPersistent );
void ELASTIC_APM_PEFREE_FUNC ( void* allocatedBlock, bool isPersistent );
#endif

#if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )

#define ELASTIC_APM_PHP_ALLOC_MEMORY_TRACKING_BEFORE( requestedSize, /* out */ actuallyRequestedSizeVar ) \
    MemoryTracker* const memTracker = getGlobalMemoryTracker(); \
    void* stackTraceAddressesBuffer[ maxCaptureStackTraceDepth ]; \
    size_t stackTraceAddressesCount = 0; \
    const bool isMemoryTrackingEnabledCached = isMemoryTrackingEnabled( memTracker ); \
    if ( isMemoryTrackingEnabledCached ) \
    { \
        if ( shouldCaptureStackTrace( memTracker ) ) \
        { \
            stackTraceAddressesCount = ELASTIC_APM_CAPTURE_STACK_TRACE( \
                    &(stackTraceAddressesBuffer[ 0 ]), \
                    ELASTIC_APM_STATIC_ARRAY_SIZE( stackTraceAddressesBuffer ) ); \
        } \
        (actuallyRequestedSizeVar) = \
                memoryTrackerCalcSizeToAlloc( memTracker, (requestedSize), stackTraceAddressesCount ); \
    }

#define ELASTIC_APM_PHP_ALLOC_MEMORY_TRACKING_AFTER( isString, requestedSize, actuallyRequestedSize, isPersistent ) \
    if ( isMemoryTrackingEnabledCached ) \
    { \
        memoryTrackerAfterAlloc( \
            memTracker, \
            (phpAllocIfFailedGotoTmpPtr), \
            (requestedSize), \
            (isPersistent), \
            (actuallyRequestedSize), \
            ELASTIC_APM_STRING_LITERAL_TO_VIEW( __FILE__ ), \
            __LINE__, \
            isString, \
            &( stackTraceAddressesBuffer[ 0 ] ), \
            stackTraceAddressesCount ); \
    }

#else // #if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )

#define ELASTIC_APM_PHP_ALLOC_MEMORY_TRACKING_BEFORE( requestedSize )
#define ELASTIC_APM_PHP_ALLOC_MEMORY_TRACKING_AFTER( isString, requestedSize, isPersistent )

#endif // #if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )

#define ELASTIC_APM_PHP_ALLOC_IF_FAILED_DO_EX( type, isString, requestedSize, isPersistent, outPtr, doOnFailure ) \
    do { \
        size_t actuallyRequestedSize = (requestedSize); \
        \
        ELASTIC_APM_PHP_ALLOC_MEMORY_TRACKING_BEFORE( requestedSize, /* out */ actuallyRequestedSize ) \
        \
        void* phpAllocIfFailedGotoTmpPtr = ELASTIC_APM_PEMALLOC_FUNC( actuallyRequestedSize, (isPersistent) ); \
        if ( phpAllocIfFailedGotoTmpPtr == NULL ) \
        { \
            resultCode = resultOutOfMemory; \
            doOnFailure; \
        } \
        (outPtr) = (type*)(phpAllocIfFailedGotoTmpPtr); \
        \
        ELASTIC_APM_PHP_ALLOC_MEMORY_TRACKING_AFTER( isString, requestedSize, actuallyRequestedSize, isPersistent ) \
    } while ( 0 )

#define ELASTIC_APM_PHP_ALLOC_IF_FAILED_GOTO_EX( type, isString, requestedSize, isPersistent, outPtr ) \
    ELASTIC_APM_PHP_ALLOC_IF_FAILED_DO_EX( type, isString, requestedSize, isPersistent, outPtr, /* doOnFailure: */ goto failure )


//#define ELASTIC_APM_EMALLOC_INSTANCE_IF_FAILED_GOTO( type, outPtr ) \
//    ELASTIC_APM_PHP_ALLOC_IF_FAILED_GOTO_EX( type, /* isString: */ false, sizeof( type ), /* isPersistent: */ false, outPtr )

#define ELASTIC_APM_PEMALLOC_INSTANCE_IF_FAILED_GOTO( type, outPtr ) \
    ELASTIC_APM_PHP_ALLOC_IF_FAILED_GOTO_EX( type, /* isString: */ false, sizeof( type ), /* isPersistent: */ true,  outPtr )


#define ELASTIC_APM_PHP_ALLOC_ARRAY_IF_FAILED_DO( type, isString, arrayNumberOfElements, isPersistent, outPtr, doOnFailure ) \
    ELASTIC_APM_PHP_ALLOC_IF_FAILED_DO_EX( type, isString, sizeof( type ) * (arrayNumberOfElements), isPersistent, outPtr, doOnFailure )

#define ELASTIC_APM_PHP_ALLOC_ARRAY_IF_FAILED_GOTO( type, isString, arrayNumberOfElements, isPersistent, outPtr ) \
    ELASTIC_APM_PHP_ALLOC_ARRAY_IF_FAILED_DO( type, isString, arrayNumberOfElements, isPersistent, outPtr, /* doOnFailure: */ goto failure )

#define ELASTIC_APM_EMALLOC_ARRAY_IF_FAILED_GOTO( type, arrayNumberOfElements, outPtr ) \
    ELASTIC_APM_PHP_ALLOC_ARRAY_IF_FAILED_GOTO( type, /* isString: */ false, arrayNumberOfElements, /* isPersistent */ false, outPtr )

#define ELASTIC_APM_PEMALLOC_ARRAY_IF_FAILED_GOTO( type, arrayNumberOfElements, outPtr ) \
    ELASTIC_APM_PHP_ALLOC_ARRAY_IF_FAILED_GOTO( type, /* isString: */ false, arrayNumberOfElements, /* isPersistent */ true,  outPtr )

#define ELASTIC_APM_EMALLOC_STRING_IF_FAILED_GOTO( stringBufferSizeInclTermZero, outPtr ) \
    ELASTIC_APM_PHP_ALLOC_ARRAY_IF_FAILED_GOTO( char, /* isString: */ true, stringBufferSizeInclTermZero, /* isPersistent */ false, outPtr )

#define ELASTIC_APM_PEMALLOC_STRING_IF_FAILED_GOTO( stringBufferSizeInclTermZero, outPtr ) \
    ELASTIC_APM_PHP_ALLOC_ARRAY_IF_FAILED_GOTO( char, /* isString: */ true, stringBufferSizeInclTermZero, /* isPersistent */ true, outPtr )

#define ELASTIC_APM_PHP_ALLOC_DUP_STRING_IF_FAILED_DO( srcStr, isPersistent, outPtr, doOnFailure ) \
    do { \
        ELASTIC_APM_ASSERT( (srcStr) != NULL, "" ); \
        char* elasticApmPemallocDupStringTempPtr = NULL; \
        ELASTIC_APM_PHP_ALLOC_ARRAY_IF_FAILED_DO( \
                char, \
                /* isString: */ true, \
                strlen( (srcStr) ) + 1, \
                isPersistent, \
                elasticApmPemallocDupStringTempPtr, \
                doOnFailure ); \
        strcpy( elasticApmPemallocDupStringTempPtr, (srcStr) ); \
        (outPtr) = elasticApmPemallocDupStringTempPtr; \
    } while ( 0 )

#define ELASTIC_APM_PHP_ALLOC_DUP_STRING_IF_FAILED_GOTO( srcStr, isPersistent, outPtr ) \
    ELASTIC_APM_PHP_ALLOC_DUP_STRING_IF_FAILED_DO( srcStr, isPersistent, outPtr, /* doOnFailure: */ goto failure )

#define ELASTIC_APM_EMALLOC_DUP_STRING_IF_FAILED_GOTO( srcStr, outPtr ) \
    ELASTIC_APM_PHP_ALLOC_DUP_STRING_IF_FAILED_GOTO( srcStr, /* isPersistent */ false, outPtr )

#define ELASTIC_APM_PEMALLOC_DUP_STRING_IF_FAILED_GOTO( srcStr, outPtr ) \
    ELASTIC_APM_PHP_ALLOC_DUP_STRING_IF_FAILED_GOTO( srcStr, /* isPersistent */ true,  outPtr )

#define ELASTIC_APM_PHP_ALLOC_DUP_STRING_VIEW_IF_FAILED_DO( srcStrBegin, srcStrLen, isPersistent, outPtr, doOnFailure ) \
    do { \
        ELASTIC_APM_ASSERT( (srcStrBegin) != NULL, "" ); \
        char* elasticApmPemallocDupStringTempPtr = NULL; \
        ELASTIC_APM_PHP_ALLOC_ARRAY_IF_FAILED_DO( \
            char, \
            /* isString: */ true, \
            (srcStrLen) + 1, \
            isPersistent, \
            elasticApmPemallocDupStringTempPtr, \
            doOnFailure ); \
        strncpy( elasticApmPemallocDupStringTempPtr, (srcStrBegin), (srcStrLen) ); \
        elasticApmPemallocDupStringTempPtr[ (srcStrLen) ] = '\0'; \
        (outPtr) = elasticApmPemallocDupStringTempPtr; \
    } while ( 0 )

#define ELASTIC_APM_PEMALLOC_DUP_STRING_VIEW_IF_FAILED_GOTO( srcStrBegin, srcStrLen, outPtr ) \
    ELASTIC_APM_PHP_ALLOC_DUP_STRING_VIEW_IF_FAILED_DO( srcStrBegin, srcStrLen, /* isPersistent */ true,  outPtr, /* doOnFailure: */ goto failure )

static const UInt32 poisonPattern = 0xDEADBEEF;

static inline
void poisonMemoryRange( Byte* rangeBegin, size_t rangeSize )
{
    const Byte* poisonPatternBegin = (const Byte*)&poisonPattern;
    ELASTIC_APM_FOR_EACH_INDEX( i, rangeSize )
        rangeBegin[ i ] = poisonPatternBegin[ i % sizeof( poisonPattern ) ];
}

#if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )

#define ELASTIC_APM_PHP_FREE_MEMORY_TRACKING_BEFORE( requestedSize, isPersistent, ptr ) \
    MemoryTracker* const memTracker = getGlobalMemoryTracker(); \
    size_t originallyRequestedSize = 0; \
    size_t possibleActuallyRequestedSize = 0; \
    if ( isMemoryTrackingEnabled( memTracker ) ) \
    { \
        originallyRequestedSize = (requestedSize); \
        possibleActuallyRequestedSize = originallyRequestedSize; \
        memoryTrackerBeforeFree( memTracker, (ptr), originallyRequestedSize, (isPersistent), &possibleActuallyRequestedSize ); \
    } \
    \
    if ( possibleActuallyRequestedSize != 0 && getGlobalInternalChecksLevel() >= internalChecksLevel_2 ) \
    { \
        if ( getGlobalInternalChecksLevel() > memoryTrackingLevel_eachAllocationWithStackTrace ) \
            ELASTIC_APM_ASSERT_VALID_MEMORY_TRACKER( memTracker ); \
        \
        poisonMemoryRange( (Byte*)(ptr), possibleActuallyRequestedSize ); \
        \
        if ( getGlobalInternalChecksLevel() > memoryTrackingLevel_eachAllocationWithStackTrace ) \
            ELASTIC_APM_ASSERT_VALID_MEMORY_TRACKER( memTracker ); \
    }

#else // #if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )

#define ELASTIC_APM_PHP_FREE_MEMORY_TRACKING_BEFORE( requestedSize, isPersistent, ptr )

#endif // #if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )

#define ELASTIC_APM_PHP_FREE_AND_SET_TO_NULL( type, requestedSize, isPersistent, ptr ) \
    do { \
        if ( (ptr) != NULL ) \
        { \
            ELASTIC_APM_PHP_FREE_MEMORY_TRACKING_BEFORE( requestedSize, isPersistent, ptr ) \
            \
            ELASTIC_APM_PEFREE_FUNC( (void*)(ptr), (isPersistent) ); \
            (ptr) = (type*)(NULL); \
        } \
    } while ( 0 )


//#define ELASTIC_APM_EFREE_INSTANCE_AND_SET_TO_NULL( type, ptr ) \
//    ELASTIC_APM_PHP_FREE_AND_SET_TO_NULL( type, sizeof( type ), /* isPersistent */ false, ptr )

#define ELASTIC_APM_PEFREE_INSTANCE_AND_SET_TO_NULL( type, ptr ) \
    ELASTIC_APM_PHP_FREE_AND_SET_TO_NULL( type, sizeof( type ), /* isPersistent */ true, ptr )


#define ELASTIC_APM_EFREE_ARRAY_AND_SET_TO_NULL( type, arrayNumberOfElements, ptr ) \
    ELASTIC_APM_PHP_FREE_AND_SET_TO_NULL( type, sizeof( type ) * (arrayNumberOfElements), /* isPersistent */ false, ptr )

#define ELASTIC_APM_PEFREE_ARRAY_AND_SET_TO_NULL( type, arrayNumberOfElements, ptr ) \
    ELASTIC_APM_PHP_FREE_AND_SET_TO_NULL( type, sizeof( type ) * (arrayNumberOfElements), /* isPersistent */ true, ptr )


#define ELASTIC_APM_EFREE_STRING_SIZE_AND_SET_TO_NULL( stringBufferSizeInclTermZero, ptr ) \
    ELASTIC_APM_EFREE_ARRAY_AND_SET_TO_NULL( char, stringBufferSizeInclTermZero, ptr )

#define ELASTIC_APM_PEFREE_STRING_SIZE_AND_SET_TO_NULL( stringBufferSizeInclTermZero, ptr ) \
    ELASTIC_APM_PEFREE_ARRAY_AND_SET_TO_NULL( char, stringBufferSizeInclTermZero, ptr )

#define ELASTIC_APM_EFREE_STRING_AND_SET_TO_NULL( ptr ) \
    ELASTIC_APM_EFREE_STRING_SIZE_AND_SET_TO_NULL( ( (ptr) == NULL ) ? 0 : ( strlen( ptr ) + 1 ), ptr )

#define ELASTIC_APM_PEFREE_STRING_AND_SET_TO_NULL( ptr ) \
    ELASTIC_APM_PEFREE_STRING_SIZE_AND_SET_TO_NULL( ( (ptr) == NULL ) ? 0 : ( strlen( ptr ) + 1 ), ptr )


#define ELASTIC_APM_MALLOC_IF_FAILED_DO_EX( type, requestedSize, outPtr, doOnFailure ) \
    do { \
        void* mallocIfFailedDoExPtr = malloc( requestedSize ); \
        if ( mallocIfFailedDoExPtr == NULL ) \
        { \
            resultCode = resultOutOfMemory; \
            doOnFailure; \
        } \
        (outPtr) = (type*)(mallocIfFailedDoExPtr); \
    } while ( 0 )

#define ELASTIC_APM_MALLOC_IF_FAILED_GOTO( type, requestedSize, outPtr ) \
    ELASTIC_APM_MALLOC_IF_FAILED_DO_EX( type, requestedSize, outPtr, /* doOnFailure: */ goto failure )

#define ELASTIC_APM_MALLOC_INSTANCE_IF_FAILED_GOTO( type, outPtr ) \
    ELASTIC_APM_MALLOC_IF_FAILED_GOTO( type, sizeof( type ), outPtr )

#define ELASTIC_APM_FREE_AND_SET_TO_NULL( type, requestedSize, ptr ) \
    do { \
        if ( (ptr) != NULL ) \
        { \
            free( (void*)(ptr) ); \
            (ptr) = (type*)(NULL); \
        }\
    } while ( 0 )

#define ELASTIC_APM_FREE_INSTANCE_AND_SET_TO_NULL( type, ptr ) \
    ELASTIC_APM_FREE_AND_SET_TO_NULL( type, sizeof( type ), ptr )
