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

#pragma once

#include <stdbool.h>
#include "basic_types.h"
#include "MemoryTracker.h"
#include "internal_checks.h"

#if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )
#   include "platform.h" // ELASTICAPM_CAPTURE_STACK_TRACE
#endif

#ifndef ELASTICAPM_PEMALLOC_FUNC
#   include <php.h>
#   define ELASTICAPM_PEMALLOC_FUNC pemalloc
#   define ELASTICAPM_PEFREE_FUNC pefree
#else
// Declare to avoid warnings
void* ELASTICAPM_PEMALLOC_FUNC ( size_t requestedSize, bool isPersistent );
void ELASTICAPM_PEFREE_FUNC ( void* allocatedBlock, bool isPersistent );
#endif

#if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )

#define ELASTICAPM_PHP_ALLOC_MEMORY_TRACKING_BEFORE( requestedSize ) \
    MemoryTracker* const memTracker = getGlobalMemoryTracker(); \
    void* stackTraceAddressesBuffer[ maxCaptureStackTraceDepth ]; \
    size_t stackTraceAddressesCount = 0; \
    const bool isMemoryTrackingEnabledCached = isMemoryTrackingEnabled( memTracker ); \
    if ( isMemoryTrackingEnabledCached ) \
    { \
        if ( shouldCaptureStackTrace( memTracker ) ) \
        { \
            stackTraceAddressesCount = ELASTICAPM_CAPTURE_STACK_TRACE( \
                    &(stackTraceAddressesBuffer[ 0 ]), \
                    ELASTICAPM_STATIC_ARRAY_SIZE( stackTraceAddressesBuffer ) ); \
        } \
        actuallyRequestedSize = \
                memoryTrackerCalcSizeToAlloc( memTracker, (requestedSize), stackTraceAddressesCount ); \
    }

#define ELASTICAPM_PHP_ALLOC_MEMORY_TRACKING_AFTER( isString, requestedSize, isPersistent ) \
    if ( isMemoryTrackingEnabledCached ) \
    { \
        memoryTrackerAfterAlloc( \
            memTracker, \
            (phpAllocIfFailedGotoTmpPtr), \
            (requestedSize), \
            (isPersistent), \
            actuallyRequestedSize, \
            ELASTICAPM_STRING_LITERAL_TO_VIEW( __FILE__ ), \
            __LINE__, \
            isString, \
            &( stackTraceAddressesBuffer[ 0 ] ), \
            stackTraceAddressesCount ); \
    }

#else // #if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )

#define ELASTICAPM_PHP_ALLOC_MEMORY_TRACKING_BEFORE( requestedSize )
#define ELASTICAPM_PHP_ALLOC_MEMORY_TRACKING_AFTER( isString, requestedSize, isPersistent )

#endif // #if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )

#define ELASTICAPM_PHP_ALLOC_IF_FAILED_DO_EX( type, isString, requestedSize, isPersistent, outPtr, doOnFailure ) \
    do { \
        size_t actuallyRequestedSize = requestedSize; \
        \
        ELASTICAPM_PHP_ALLOC_MEMORY_TRACKING_BEFORE( requestedSize ) \
        \
        void* phpAllocIfFailedGotoTmpPtr = ELASTICAPM_PEMALLOC_FUNC( actuallyRequestedSize, (isPersistent) ); \
        if ( phpAllocIfFailedGotoTmpPtr == NULL ) \
        { \
            resultCode = resultOutOfMemory; \
            doOnFailure; \
        } \
        (outPtr) = (type*)(phpAllocIfFailedGotoTmpPtr); \
        \
        ELASTICAPM_PHP_ALLOC_MEMORY_TRACKING_AFTER( isString, requestedSize, isPersistent ) \
    } while ( 0 )

#define ELASTICAPM_PHP_ALLOC_IF_FAILED_GOTO_EX( type, isString, requestedSize, isPersistent, outPtr ) \
    ELASTICAPM_PHP_ALLOC_IF_FAILED_DO_EX( type, isString, requestedSize, isPersistent, outPtr, /* doOnFailure: */ goto failure )


#define ELASTICAPM_EMALLOC_INSTANCE_IF_FAILED_GOTO( type, outPtr ) \
    ELASTICAPM_PHP_ALLOC_IF_FAILED_GOTO_EX( type, /* isString: */ false, sizeof( type ), /* isPersistent: */ false, outPtr )

#define ELASTICAPM_PEMALLOC_INSTANCE_IF_FAILED_GOTO( type, outPtr ) \
    ELASTICAPM_PHP_ALLOC_IF_FAILED_GOTO_EX( type, /* isString: */ false, sizeof( type ), /* isPersistent: */ true,  outPtr )


#define ELASTICAPM_PHP_ALLOC_ARRAY_IF_FAILED_DO( type, isString, arrayNumberOfElements, isPersistent, outPtr, doOnFailure ) \
    ELASTICAPM_PHP_ALLOC_IF_FAILED_DO_EX( type, isString, sizeof( type ) * (arrayNumberOfElements), isPersistent, outPtr, doOnFailure )

#define ELASTICAPM_PHP_ALLOC_ARRAY_IF_FAILED_GOTO( type, isString, arrayNumberOfElements, isPersistent, outPtr ) \
    ELASTICAPM_PHP_ALLOC_ARRAY_IF_FAILED_DO( type, isString, arrayNumberOfElements, isPersistent, outPtr, /* doOnFailure: */ goto failure )

#define ELASTICAPM_EMALLOC_ARRAY_IF_FAILED_GOTO( type, arrayNumberOfElements, outPtr ) \
    ELASTICAPM_PHP_ALLOC_ARRAY_IF_FAILED_GOTO( type, /* isString: */ false, arrayNumberOfElements, /* isPersistent */ false, outPtr )

#define ELASTICAPM_PEMALLOC_ARRAY_IF_FAILED_GOTO( type, arrayNumberOfElements, outPtr ) \
    ELASTICAPM_PHP_ALLOC_ARRAY_IF_FAILED_GOTO( type, /* isString: */ false, arrayNumberOfElements, /* isPersistent */ true,  outPtr )

#define ELASTICAPM_EMALLOC_STRING_IF_FAILED_GOTO( stringBufferSizeInclTermZero, outPtr ) \
    ELASTICAPM_PHP_ALLOC_ARRAY_IF_FAILED_GOTO( char, /* isString: */ true, stringBufferSizeInclTermZero, /* isPersistent */ false, outPtr )

#define ELASTICAPM_PEMALLOC_STRING_IF_FAILED_GOTO( stringBufferSizeInclTermZero, outPtr ) \
    ELASTICAPM_PHP_ALLOC_ARRAY_IF_FAILED_GOTO( char, /* isString: */ true, stringBufferSizeInclTermZero, /* isPersistent */ true, outPtr )


#define ELASTICAPM_PHP_ALLOC_DUP_STRING_IF_FAILED_DO( srcStr, isPersistent, outPtr, doOnFailure ) \
    do { \
        ELASTICAPM_ASSERT( (srcStr) != NULL ); \
        char* elasticApmPemallocDupStringTempPtr = NULL; \
        ELASTICAPM_PHP_ALLOC_ARRAY_IF_FAILED_DO( \
                char, \
                /* isString: */ true, \
                strlen( (srcStr) ) + 1, \
                isPersistent, \
                elasticApmPemallocDupStringTempPtr, \
                doOnFailure ); \
        strcpy( elasticApmPemallocDupStringTempPtr, (srcStr) ); \
        (outPtr) = elasticApmPemallocDupStringTempPtr; \
    } while ( 0 )

#define ELASTICAPM_PHP_ALLOC_DUP_STRING_IF_FAILED_GOTO( srcStr, isPersistent, outPtr ) \
    ELASTICAPM_PHP_ALLOC_DUP_STRING_IF_FAILED_DO( srcStr, isPersistent, outPtr, /* doOnFailure: */ goto failure )

#define ELASTICAPM_EMALLOC_DUP_STRING_IF_FAILED_GOTO( srcStr, outPtr ) \
    ELASTICAPM_PHP_ALLOC_DUP_STRING_IF_FAILED_GOTO( srcStr, /* isPersistent */ false, outPtr )

#define ELASTICAPM_PEMALLOC_DUP_STRING_IF_FAILED_GOTO( srcStr, outPtr ) \
    ELASTICAPM_PHP_ALLOC_DUP_STRING_IF_FAILED_GOTO( srcStr, /* isPersistent */ true,  outPtr )


static const UInt32 poisonPattern = 0xDEADBEEF;

static inline
void poisonMemoryRange( Byte* rangeBegin, size_t rangeSize )
{
    const Byte* poisonPatternBegin = (const Byte*)&poisonPattern;
    ELASTICAPM_FOR_EACH_INDEX( i, rangeSize )
        rangeBegin[ i ] = poisonPatternBegin[ i % sizeof( poisonPattern ) ];
}

#if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )

#define ELASTICAPM_PHP_FREE_MEMORY_TRACKING_BEFORE( requestedSize, isPersistent, ptr ) \
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
            ELASTICAPM_ASSERT_VALID_MEMORY_TRACKER( memTracker ); \
        \
        poisonMemoryRange( (Byte*)(ptr), possibleActuallyRequestedSize ); \
        \
        if ( getGlobalInternalChecksLevel() > memoryTrackingLevel_eachAllocationWithStackTrace ) \
            ELASTICAPM_ASSERT_VALID_MEMORY_TRACKER( memTracker ); \
    }

#else // #if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )

#define ELASTICAPM_PHP_FREE_MEMORY_TRACKING_BEFORE( requestedSize, isPersistent, ptr )

#endif // #if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )

#define ELASTICAPM_PHP_FREE_AND_SET_TO_NULL( type, requestedSize, isPersistent, ptr ) \
    do { \
        if ( (ptr) != NULL ) \
        { \
            ELASTICAPM_PHP_FREE_MEMORY_TRACKING_BEFORE( requestedSize, isPersistent, ptr ) \
            \
            ELASTICAPM_PEFREE_FUNC( (void*)(ptr), (isPersistent) ); \
            (ptr) = (type*)(NULL); \
        } \
    } while ( 0 )


#define ELASTICAPM_EFREE_INSTANCE_AND_SET_TO_NULL( type, ptr ) \
    ELASTICAPM_PHP_FREE_AND_SET_TO_NULL( type, sizeof( type ), /* isPersistent */ false, ptr )

#define ELASTICAPM_PEFREE_INSTANCE_AND_SET_TO_NULL( type, ptr ) \
    ELASTICAPM_PHP_FREE_AND_SET_TO_NULL( type, sizeof( type ), /* isPersistent */ true, ptr )


#define ELASTICAPM_EFREE_ARRAY_AND_SET_TO_NULL( type, arrayNumberOfElements, ptr ) \
    ELASTICAPM_PHP_FREE_AND_SET_TO_NULL( type, sizeof( type ) * (arrayNumberOfElements), /* isPersistent */ false, ptr )

#define ELASTICAPM_PEFREE_ARRAY_AND_SET_TO_NULL( type, arrayNumberOfElements, ptr ) \
    ELASTICAPM_PHP_FREE_AND_SET_TO_NULL( type, sizeof( type ) * (arrayNumberOfElements), /* isPersistent */ true, ptr )


#define ELASTICAPM_EFREE_STRING_AND_SET_TO_NULL( stringBufferSizeInclTermZero, ptr ) \
    ELASTICAPM_EFREE_ARRAY_AND_SET_TO_NULL( char, stringBufferSizeInclTermZero, ptr )

#define ELASTICAPM_PEFREE_STRING_AND_SET_TO_NULL( stringBufferSizeInclTermZero, ptr ) \
    ELASTICAPM_PEFREE_ARRAY_AND_SET_TO_NULL( char, stringBufferSizeInclTermZero, ptr )
