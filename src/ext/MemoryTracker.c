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

#include "MemoryTracker.h"

#if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_MEM_TRACKER

#include <stddef.h>
#include "util.h"
#include "TextOutputStream.h"
#include "elastic_apm_assert.h"
#include "basic_macros.h"
#include "IntrusiveDoublyLinkedList.h"
#include "log.h"
#include "platform.h"

const char* memoryTrackingLevelNames[ numberOfMemoryTrackingLevels ] =
{
    [ memoryTrackingLevel_off ] = "OFF",
    [ memoryTrackingLevel_totalCountOnly ] = "total_count_only",
    [ memoryTrackingLevel_eachAllocation ] = "each_allocation",
    [ memoryTrackingLevel_eachAllocationWithStackTrace ] = "each_allocation_with_stack_trace",
    [ memoryTrackingLevel_all ] = "ALL"
};

static const UInt32 prefixMagicExpectedValue = 0xCAFEBABE;
static const UInt32 suffixMagicExpectedValue = 0x1CEB00DA;
static const UInt32 invalidMagicValue = 0xDEADBEEF;

enum { maxNumberOfLeakedAllocationsToReport = 10 };
static const size_t maxNumberOfBytesFromLeakedAllocationToReport = 100;

struct EmbeddedTrackingDataHeader
{
    UInt32 prefixMagic;

    IntrusiveDoublyLinkedListNode intrusiveNode;

    String fileName;
    UInt lineNumber;
    size_t originallyRequestedSize;
    bool isString;

    size_t stackTraceAddressesCount;
};
typedef struct EmbeddedTrackingDataHeader EmbeddedTrackingDataHeader;

struct DeserializedTrackingData
{
#pragma clang diagnostic push
#pragma ide diagnostic ignored "OCUnusedGlobalDeclarationInspection"
    EmbeddedTrackingDataHeader* embedded;
    void* stackTraceAddresses[ maxCaptureStackTraceDepth ];
    UInt32 suffixMagic;
};
typedef struct DeserializedTrackingData DeserializedTrackingData;

void constructMemoryTracker( MemoryTracker* memTracker )
{
    ELASTIC_APM_ASSERT_VALID_PTR( memTracker );

    /// Set initial level to the highest value
    /// because we can only reduce memory tracking level
    /// See also reconfigureMemoryTracker() in .h
    memTracker->level = memoryTrackingLevel_all;
    memTracker->abortOnMemoryLeak = ELASTIC_APM_MEMORY_TRACKING_DEFAULT_ABORT_ON_MEMORY_LEAK;
    memTracker->allocatedPersistent = 0;
    initIntrusiveDoublyLinkedList( &memTracker->allocatedPersistentBlocks );
    memTracker->allocatedRequestScoped = 0;
    initIntrusiveDoublyLinkedList( &memTracker->allocatedRequestScopedBlocks );

    ELASTIC_APM_ASSERT_VALID_MEMORY_TRACKER( memTracker );
}

void memoryTrackerRequestInit( MemoryTracker* memTracker )
{
    memTracker->allocatedRequestScoped = 0;
}

static const size_t embeddedTrackingDataAlignment = 8;

static
size_t calcSizeBeforeTrackingData( size_t originallyRequestedSize )
{
    return calcAlignedSize( originallyRequestedSize, embeddedTrackingDataAlignment );
}

static
size_t calcStackTraceAddressesSize( size_t stackTraceAddressesCount )
{
    return stackTraceAddressesCount * ELASTIC_APM_FIELD_SIZEOF( DeserializedTrackingData, stackTraceAddresses[ 0 ] );
}

size_t memoryTrackerCalcSizeToAlloc(
        MemoryTracker* memTracker,
        size_t originallyRequestedSize,
        size_t stackTraceAddressesCount )
{
    if ( memTracker->level < memoryTrackingLevel_eachAllocation ) return originallyRequestedSize;

    return calcSizeBeforeTrackingData( originallyRequestedSize ) +
           sizeof( EmbeddedTrackingDataHeader ) +
           calcStackTraceAddressesSize( stackTraceAddressesCount ) +
           ELASTIC_APM_FIELD_SIZEOF( DeserializedTrackingData, suffixMagic );
}

static
EmbeddedTrackingDataHeader* allocatedBlockToTrackingData( const void* allocatedBlock, size_t originallyRequestedSize )
{
    return (EmbeddedTrackingDataHeader*)( ((const Byte*)allocatedBlock) + calcSizeBeforeTrackingData( originallyRequestedSize ));
}

static
const Byte* trackingDataToAllocatedBlock( const EmbeddedTrackingDataHeader* trackingData )
{
    return ((const Byte*)trackingData) - calcSizeBeforeTrackingData( trackingData->originallyRequestedSize );
}

static
void addToTrackedAllocatedBlocks(
        MemoryTracker* memTracker,
        const void* allocatedBlock,
        size_t originallyRequestedSize,
        bool isPersistent,
        StringView filePath,
        UInt lineNumber,
        bool isString,
        void* const* stackTraceAddresses,
        size_t stackTraceAddressesCount )
{
    EmbeddedTrackingDataHeader* trackingDataHeader = allocatedBlockToTrackingData( allocatedBlock, originallyRequestedSize );
    ELASTIC_APM_ZERO_STRUCT( trackingDataHeader );
    UInt64* allocated = isPersistent ? &memTracker->allocatedPersistent : &memTracker->allocatedRequestScoped;
    IntrusiveDoublyLinkedList* allocatedBlocks = isPersistent ? &memTracker->allocatedPersistentBlocks : &memTracker->allocatedRequestScopedBlocks;

    *allocated += originallyRequestedSize;
    addToIntrusiveDoublyLinkedListBack( allocatedBlocks, &trackingDataHeader->intrusiveNode );

    trackingDataHeader->prefixMagic = prefixMagicExpectedValue;
    trackingDataHeader->fileName = extractLastPartOfFilePathStringView( filePath ).begin;
    trackingDataHeader->lineNumber = lineNumber;
    trackingDataHeader->originallyRequestedSize = originallyRequestedSize;
    trackingDataHeader->isString = isString;
    trackingDataHeader->stackTraceAddressesCount = stackTraceAddressesCount;

    Byte* postHeader = ((Byte*)trackingDataHeader) + sizeof( EmbeddedTrackingDataHeader );
    if ( stackTraceAddressesCount != 0 )
    {
        memcpy( postHeader, stackTraceAddresses, calcStackTraceAddressesSize( stackTraceAddressesCount ) );
        postHeader += calcStackTraceAddressesSize( stackTraceAddressesCount );
    }

    memcpy( postHeader, &suffixMagicExpectedValue, ELASTIC_APM_FIELD_SIZEOF( DeserializedTrackingData, suffixMagic ) );
}

void memoryTrackerAfterAlloc(
        MemoryTracker* memTracker,
        const void* allocatedBlock,
        size_t originallyRequestedSize,
        bool isPersistent,
        size_t actuallyRequestedSize,
        StringView filePath,
        UInt lineNumber,
        bool isString,
        void* const* stackTraceAddresses,
        size_t stackTraceAddressesCount )
{
    ELASTIC_APM_ASSERT_VALID_MEMORY_TRACKER( memTracker );
    ELASTIC_APM_ASSERT_GE_UINT64( actuallyRequestedSize, originallyRequestedSize );
    ELASTIC_APM_ASSERT_LE_UINT64( stackTraceAddressesCount, maxCaptureStackTraceDepth );

    if ( actuallyRequestedSize > originallyRequestedSize )
    {
        addToTrackedAllocatedBlocks(
                memTracker,
                allocatedBlock,
                originallyRequestedSize,
                isPersistent,
                filePath,
                lineNumber,
                isString,
                stackTraceAddresses,
                stackTraceAddressesCount );
    }

    ELASTIC_APM_ASSERT_VALID_MEMORY_TRACKER( memTracker );
}

static inline
const char* allocType( bool isPersistent )
{
    return isPersistent ? "persistent" : "request scoped";
}

#define ELASTIC_APM_REPORT_MEMORY_CORRUPTION_AND_ABORT( fmt, ... ) \
    do { \
        ELASTIC_APM_FORCE_LOG_CRITICAL( "Memory corruption detected! " fmt , ##__VA_ARGS__ ); \
        elasticApmAbort(); \
    } while ( 0 )

static
void verifyMagic( String desc, UInt32 actual, UInt32 expected )
{
    if ( actual == expected ) return;

    ELASTIC_APM_REPORT_MEMORY_CORRUPTION_AND_ABORT(
            "Magic %s is different from expected. Actual: 0x%08"PRIX32". Expected: 0x%08"PRIX32".",
            desc, actual, expected );
}

static
void removeFromTrackedAllocatedBlocks(
        MemoryTracker* memTracker,
        const void* allocatedBlock,
        size_t originallyRequestedSize,
        IntrusiveDoublyLinkedList* allocatedBlocks,
        size_t* possibleActuallyRequestedSize )
{
    EmbeddedTrackingDataHeader* trackingDataHeader = allocatedBlockToTrackingData( allocatedBlock, originallyRequestedSize );

    verifyMagic( "prefix", trackingDataHeader->prefixMagic, prefixMagicExpectedValue );

    Byte* postHeader = ((Byte*)trackingDataHeader) + sizeof( EmbeddedTrackingDataHeader );
    postHeader += calcStackTraceAddressesSize( trackingDataHeader->stackTraceAddressesCount );
    UInt32 actualSuffixMagic;
    memcpy( &actualSuffixMagic, postHeader, sizeof( actualSuffixMagic ) );
    verifyMagic( "suffix", actualSuffixMagic, suffixMagicExpectedValue );

    *possibleActuallyRequestedSize =
            memoryTrackerCalcSizeToAlloc(memTracker, originallyRequestedSize, trackingDataHeader->stackTraceAddressesCount );

    removeCurrentNodeIntrusiveDoublyLinkedList(
            nodeToIntrusiveDoublyLinkedListIterator( allocatedBlocks, &trackingDataHeader->intrusiveNode ) );

    trackingDataHeader->prefixMagic = invalidMagicValue;
    memcpy( postHeader, &invalidMagicValue, ELASTIC_APM_FIELD_SIZEOF( DeserializedTrackingData, suffixMagic ) );
}

void memoryTrackerBeforeFree(
        MemoryTracker* memTracker,
        const void* allocatedBlock,
        size_t originallyRequestedSize,
        bool isPersistent,
        size_t* possibleActuallyRequestedSize )
{
    ELASTIC_APM_UNUSED( allocatedBlock );
    ELASTIC_APM_ASSERT_VALID_PTR( possibleActuallyRequestedSize );

    UInt64* allocated = isPersistent ? &memTracker->allocatedPersistent : &memTracker->allocatedRequestScoped;
    IntrusiveDoublyLinkedList* allocatedBlocks = isPersistent ? &memTracker->allocatedPersistentBlocks : &memTracker->allocatedRequestScopedBlocks;

    ELASTIC_APM_ASSERT( *allocated >= originallyRequestedSize
            , "Attempting to free more %s memory than allocated. Allocated: %"PRIu64". Attempting to free: %"PRIu64
            , allocType( isPersistent ), *allocated, (UInt64)originallyRequestedSize );

    *possibleActuallyRequestedSize = originallyRequestedSize;
    // Since memory tracking level can only be reduced it means that
    // if the current level (i.e., at the moment of call to free()) includes tracking for each allocation
    // then the level at the moment of call to malloc() included tracking for each allocation as well
    if ( memTracker->level >= memoryTrackingLevel_eachAllocation )
        removeFromTrackedAllocatedBlocks( memTracker, allocatedBlock, originallyRequestedSize, allocatedBlocks, possibleActuallyRequestedSize );

    *allocated -= originallyRequestedSize;
}

static
const EmbeddedTrackingDataHeader* fromIntrusiveNodeToTrackingData( const IntrusiveDoublyLinkedListNode* intrusiveListNode )
{
    return (const EmbeddedTrackingDataHeader*)( ((const Byte*) intrusiveListNode ) - offsetof( EmbeddedTrackingDataHeader, intrusiveNode ) );
}

static
void streamMemoryBlockAsString(
        const Byte* memBlock,
        size_t numberOfItemsToStream,
        TextOutputStream* txtOutStream )
{
    streamString( "`", txtOutStream );
    String memBlockAsString = (String)memBlock;
    char bufferToEscape[ escapeNonPrintableCharBufferSize ];
    ELASTIC_APM_FOR_EACH_INDEX( i, numberOfItemsToStream )
        streamPrintf( txtOutStream, "%s", escapeNonPrintableChar( memBlockAsString[ i ], bufferToEscape ) );
    streamString( "'", txtOutStream );
}

static
void streamMemoryBlockAsBinary(
        const Byte* memBlock,
        size_t numberOfItemsToStream,
        TextOutputStream* txtOutStream )
{
    streamString( "[", txtOutStream );
    ELASTIC_APM_FOR_EACH_INDEX( i, numberOfItemsToStream )
        streamPrintf( txtOutStream, " %02X", (UInt)( memBlock[ i ] ) );
    streamString( " ]", txtOutStream );
}

static
String streamMemBlockContent(
        TextOutputStream* txtOutStream,
        const EmbeddedTrackingDataHeader* trackingDataHeader )
{
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( txtOutStream, &txtOutStreamStateOnEntryStart ) )
        return ELASTIC_APM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;

    const Byte* memBlock = trackingDataToAllocatedBlock( trackingDataHeader );
    const size_t numberOfItemsToStream =
            ELASTIC_APM_MIN( trackingDataHeader->originallyRequestedSize, maxNumberOfBytesFromLeakedAllocationToReport );

    const String itemsName = trackingDataHeader->isString ? "chars" : "bytes, in hex";
    if ( numberOfItemsToStream < trackingDataHeader->originallyRequestedSize )
        streamPrintf( txtOutStream, "(first %"PRIu64" %s) ", (UInt64)numberOfItemsToStream, itemsName );

    if ( trackingDataHeader->isString )
        streamMemoryBlockAsString( memBlock, numberOfItemsToStream, txtOutStream );
    else
        streamMemoryBlockAsBinary( memBlock, numberOfItemsToStream, txtOutStream );

    return textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, txtOutStream );
}

static
String streamAllocCallStackTrace(
        TextOutputStream* txtOutStream,
        const EmbeddedTrackingDataHeader* trackingDataHeader )
{
    if ( trackingDataHeader->stackTraceAddressesCount == 0 )
        return "\t\t<STACK TRACE IS NOT CAPTURED>";

    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( txtOutStream, &txtOutStreamStateOnEntryStart ) )
        return ELASTIC_APM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;

    Byte* postHeader = ((Byte*)trackingDataHeader) + sizeof( EmbeddedTrackingDataHeader );
    ELASTIC_APM_ASSERT_LE_UINT64( trackingDataHeader->stackTraceAddressesCount, maxCaptureStackTraceDepth );
    void* stackTraceAddresses[ maxCaptureStackTraceDepth ];
    memcpy( stackTraceAddresses, postHeader, sizeof( void* ) * trackingDataHeader->stackTraceAddressesCount );
    streamStackTrace(
            &(stackTraceAddresses[ 0 ]),
            trackingDataHeader->stackTraceAddressesCount,
            /* linePrefix: */ "\t\t",
            txtOutStream );

    return textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, txtOutStream );
}

static
void reportAllocation( const IntrusiveDoublyLinkedListNode* intrusiveListNode, size_t allocationIndex, size_t numberOfAllocations )
{
    const EmbeddedTrackingDataHeader* trackingDataHeader = fromIntrusiveNodeToTrackingData( intrusiveListNode );

    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE * 10 ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_FORCE_LOG_CRITICAL(
            "Allocation #%"PRIu64" (out of %"PRIu64"):"
            " Source location: %s:%u. Originally requested allocation size: %"PRIu64"."
            " Content: %s.\n"
            "\t+-> Allocation call stack trace:\n%s",
            (UInt64)(allocationIndex + 1), (UInt64)numberOfAllocations,
            trackingDataHeader->fileName, trackingDataHeader->lineNumber,
            (UInt64)trackingDataHeader->originallyRequestedSize,
            streamMemBlockContent( &txtOutStream, trackingDataHeader ),
            streamAllocCallStackTrace( &txtOutStream, trackingDataHeader ) );
}

#ifdef ELASTIC_APM_ON_MEMORY_LEAK_CUSTOM_FUNC
// Declare to avoid warnings
void ELASTIC_APM_ON_MEMORY_LEAK_CUSTOM_FUNC();
#endif

static
void verifyBalanceIsZero( const MemoryTracker* memTracker, String whenDesc, UInt64 allocated, bool isPersistent )
{
    if ( allocated == 0 )
    {
        return;
    }

    const IntrusiveDoublyLinkedList* allocatedBlocks = isPersistent ? &memTracker->allocatedPersistentBlocks : &memTracker->allocatedRequestScopedBlocks;
    const size_t numberOfAllocations = calcIntrusiveDoublyLinkedListSize( allocatedBlocks );
    const size_t numberOfAllocationsToReport = ELASTIC_APM_MIN( numberOfAllocations, maxNumberOfLeakedAllocationsToReport );
    const IntrusiveDoublyLinkedListNode* allocationsToReport[ maxNumberOfLeakedAllocationsToReport ];

    // Copy allocation nodes we are going to report
    // because the code below might do more allocations
    {
        size_t allocationIndex = 0;
        ELASTIC_APM_FOR_EACH_IN_INTRUSIVE_LINKED_LIST( allocationsIt, allocatedBlocks )
        {
            allocationsToReport[ allocationIndex++ ] = currentNodeIntrusiveDoublyLinkedList( allocationsIt );
            if ( allocationIndex == numberOfAllocationsToReport ) break;
        }
    }

    ELASTIC_APM_FORCE_LOG_CRITICAL(
            "Memory leak detected! On %s amount of allocated %s memory should be 0, instead it is %"PRIu64,
            whenDesc, allocType( isPersistent ), allocated );

    ELASTIC_APM_FORCE_LOG_CRITICAL(
            "Number of allocations not freed: %"PRIu64 ". Following are the first %"PRIu64 " not freed allocation(s)", (UInt64) numberOfAllocations, (UInt64) numberOfAllocationsToReport );

    ELASTIC_APM_FOR_EACH_INDEX( allocationIndex, numberOfAllocationsToReport)
        reportAllocation( allocationsToReport[ allocationIndex ], allocationIndex, numberOfAllocations );

    #ifdef ELASTIC_APM_ON_MEMORY_LEAK_CUSTOM_FUNC
    ELASTIC_APM_ON_MEMORY_LEAK_CUSTOM_FUNC();
    #else
    if ( memTracker->abortOnMemoryLeak )
    {
        ELASTIC_APM_FORCE_LOG_CRITICAL("Aborting on memory leak...");
        elasticApmAbort();
    }
    #endif
}

void memoryTrackerRequestShutdown( MemoryTracker* memTracker )
{
    verifyBalanceIsZero(
            memTracker,
            "request shutdown",
            memTracker->allocatedRequestScoped,
            /* isPersistent */ false );
}

void destructMemoryTracker( MemoryTracker* memTracker )
{
    if ( isMemoryTrackingEnabled( memTracker ) )
    {
        verifyBalanceIsZero(
                memTracker,
                "module shutdown",
                memTracker->allocatedPersistent,
                /* isPersistent */ true );
    }

    memTracker->level = memoryTrackingLevel_off;
}

MemoryTrackingLevel internalChecksToMemoryTrackingLevel( InternalChecksLevel internalChecksLevel )
{
    ELASTIC_APM_STATIC_ASSERT( memoryTrackingLevel_not_set == internalChecksLevel_not_set );
    ELASTIC_APM_STATIC_ASSERT( numberOfMemoryTrackingLevels <= numberOfInternalChecksLevels );

    ELASTIC_APM_ASSERT_IN_INCLUSIVE_RANGE_UINT64( internalChecksLevel_not_set, internalChecksLevel, internalChecksLevel_all );

    if ( internalChecksLevel >= internalChecksLevel_all ) return memoryTrackingLevel_all;
    if ( internalChecksLevel < ( memoryTrackingLevel_all - 1 ) ) return (MemoryTrackingLevel)internalChecksLevel;
    return (MemoryTrackingLevel)( memoryTrackingLevel_all - 1 );
}

String streamMemoryTrackingLevel( MemoryTrackingLevel level, TextOutputStream* txtOutStream )
{
    if ( level == memoryTrackingLevel_not_set )
        return streamStringView( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "not_set" ), txtOutStream );

    if ( level >= numberOfMemoryTrackingLevels )
        return streamInt( level, txtOutStream );

    return streamString( memoryTrackingLevelNames[ level ], txtOutStream );
}

#endif // #if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )
