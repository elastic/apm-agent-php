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

#include "MemoryTracker.h"

#if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )

#include <stddef.h>
#include "util.h"
#include "TextOutputStream.h"
#include "elasticapm_assert.h"
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
    EmbeddedTrackingDataHeader* embedded;

    void* stackTraceAddresses[ maxCaptureStackTraceDepth ];

    UInt32 suffixMagic;
};
typedef struct DeserializedTrackingData DeserializedTrackingData;

void constructMemoryTracker( MemoryTracker* memTracker )
{
    ELASTICAPM_ASSERT_VALID_PTR( memTracker );

    /// Set initial level to the highest value
    /// because we can only reduce memory tracking level
    /// See also reconfigureMemoryTracker() in .h
    memTracker->level = memoryTrackingLevel_all;
    memTracker->abortOnMemoryLeak = ELASTICAPM_MEMORY_TRACKING_DEFAULT_ABORT_ON_MEMORY_LEAK;
    memTracker->allocatedPersistent = 0;
    memTracker->allocatedRequestScoped = 0;
    initIntrusiveDoublyLinkedList( &memTracker->allocatedBlocks );

    ELASTICAPM_ASSERT_VALID_MEMORY_TRACKER( memTracker );
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
    return stackTraceAddressesCount * ELASTICAPM_FIELD_SIZEOF( DeserializedTrackingData, stackTraceAddresses[ 0 ] );
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
           ELASTICAPM_FIELD_SIZEOF( DeserializedTrackingData, suffixMagic );
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
        StringView filePath,
        UInt lineNumber,
        bool isString,
        void* const* stackTraceAddresses,
        size_t stackTraceAddressesCount )
{
    EmbeddedTrackingDataHeader* trackingDataHeader = allocatedBlockToTrackingData( allocatedBlock, originallyRequestedSize );
    ELASTICAPM_ZERO_STRUCT( trackingDataHeader );

    addToIntrusiveDoublyLinkedListBack( &memTracker->allocatedBlocks, &trackingDataHeader->intrusiveNode );

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

    memcpy( postHeader, &suffixMagicExpectedValue, ELASTICAPM_FIELD_SIZEOF( DeserializedTrackingData, suffixMagic ) );
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
    ELASTICAPM_ASSERT_VALID_MEMORY_TRACKER( memTracker );
    ELASTICAPM_ASSERT( actuallyRequestedSize >= originallyRequestedSize );
    ELASTICAPM_ASSERT( stackTraceAddressesCount <= maxCaptureStackTraceDepth );

    UInt64* allocated = isPersistent ? &memTracker->allocatedPersistent : &memTracker->allocatedRequestScoped;
    *allocated += originallyRequestedSize;

    if ( actuallyRequestedSize > originallyRequestedSize )
        addToTrackedAllocatedBlocks(
                memTracker,
                allocatedBlock,
                originallyRequestedSize,
                filePath,
                lineNumber,
                isString,
                stackTraceAddresses,
                stackTraceAddressesCount );

    ELASTICAPM_ASSERT_VALID_MEMORY_TRACKER( memTracker );
}

static inline
const char* allocType( bool isPersistent )
{
    return isPersistent ? "persistent" : "request scoped";
}

#define ELASTICAPM_REPORT_MEMORY_CORRUPTION_AND_ABORT( fmt, ... ) \
    do { \
        ELASTICAPM_FORCE_LOG_CRITICAL( "Memory corruption detected! " fmt , ##__VA_ARGS__ ); \
        elasticApmAbort(); \
    } while ( 0 )

static
void verifyMagic( String desc, UInt32 actual, UInt32 expected )
{
    if ( actual == expected ) return;

    ELASTICAPM_REPORT_MEMORY_CORRUPTION_AND_ABORT(
            "Magic %s is different from expected. Actual: 0x%08"PRIX32". Expected: 0x%08"PRIX32".",
            desc, actual, expected );
}

static
void removeFromTrackedAllocatedBlocks(
        MemoryTracker* memTracker,
        const void* allocatedBlock,
        size_t originallyRequestedSize,
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
            nodeToIntrusiveDoublyLinkedListIterator( &memTracker->allocatedBlocks, &trackingDataHeader->intrusiveNode ) );

    trackingDataHeader->prefixMagic = invalidMagicValue;
    memcpy( postHeader, &invalidMagicValue, ELASTICAPM_FIELD_SIZEOF( DeserializedTrackingData, suffixMagic ) );
}

void memoryTrackerBeforeFree(
        MemoryTracker* memTracker,
        const void* allocatedBlock,
        size_t originallyRequestedSize,
        bool isPersistent,
        size_t* possibleActuallyRequestedSize )
{
    ELASTICAPM_UNUSED( allocatedBlock );
    ELASTICAPM_ASSERT_VALID_PTR( possibleActuallyRequestedSize );

    char txtOutStreamBuf[ ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    UInt64* allocated = isPersistent ? &memTracker->allocatedPersistent : &memTracker->allocatedRequestScoped;

    ELASTICAPM_ASSERT( *allocated >= originallyRequestedSize,
            streamPrintf( &txtOutStream,
                    "Attempting to free more %s memory than allocated."
                    " Allocated: %"PRIu64". Attempting to free: %"PRIu64,
                    allocType( isPersistent ),
                    *allocated, (UInt64)originallyRequestedSize ) );

    *possibleActuallyRequestedSize = originallyRequestedSize;
    // Since memory tracking level can only be reduced it means that
    // if the current level (i.e., at the moment of call to free()) includes tracking for each allocation
    // then the level at the moment of call to malloc() included tracking for each allocation as well
    if ( memTracker->level >= memoryTrackingLevel_eachAllocation )
        removeFromTrackedAllocatedBlocks( memTracker, allocatedBlock, originallyRequestedSize, possibleActuallyRequestedSize );

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
    ELASTICAPM_FOR_EACH_INDEX( i, numberOfItemsToStream )
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
    ELASTICAPM_FOR_EACH_INDEX( i, numberOfItemsToStream )
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
        return ELASTICAPM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;

    const Byte* memBlock = trackingDataToAllocatedBlock( trackingDataHeader );
    const size_t numberOfItemsToStream =
            ELASTICAPM_MIN( trackingDataHeader->originallyRequestedSize, maxNumberOfBytesFromLeakedAllocationToReport );

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
        return ELASTICAPM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;

    Byte* postHeader = ((Byte*)trackingDataHeader) + sizeof( EmbeddedTrackingDataHeader );
    ELASTICAPM_ASSERT( trackingDataHeader->stackTraceAddressesCount <= maxCaptureStackTraceDepth );
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
void reportAllocation(
        const IntrusiveDoublyLinkedListNode* intrusiveListNode,
        size_t allocationIndex,
        size_t numberOfAllocations )
{
    const EmbeddedTrackingDataHeader* trackingDataHeader = fromIntrusiveNodeToTrackingData( intrusiveListNode );

    char txtOutStreamBuf[ ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE * 10 ];
    TextOutputStream txtOutStream = ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTICAPM_FORCE_LOG_CRITICAL(
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

#ifdef ELASTICAPM_ON_MEMORY_LEAK_CUSTOM_FUNC
// Declare to avoid warnings
void ELASTICAPM_ON_MEMORY_LEAK_CUSTOM_FUNC();
#endif

static
void verifyBalanceIsZero( const MemoryTracker* memTracker, String whenDesc, UInt64 allocated, bool isPersistent )
{
    if ( allocated == 0 ) return;

    const size_t numberOfAllocations = calcIntrusiveDoublyLinkedListSize( &memTracker->allocatedBlocks );
    const size_t numberOfAllocationsToReport = ELASTICAPM_MIN( numberOfAllocations, maxNumberOfLeakedAllocationsToReport );
    const IntrusiveDoublyLinkedListNode* allocationsToReport[ maxNumberOfLeakedAllocationsToReport ];

    // Copy allocation nodes we are going to report
    // because the code below might do more allocations
    {
        size_t allocationIndex = 0;
        ELASTICAPM_FOR_EACH_IN_INTRUSIVE_LINKED_LIST( allocationsIt, &memTracker->allocatedBlocks )
        {
            allocationsToReport[ allocationIndex++ ] = currentNodeIntrusiveDoublyLinkedList( allocationsIt );
            if ( allocationIndex == numberOfAllocationsToReport ) break;
        }
    }

    ELASTICAPM_FORCE_LOG_CRITICAL(
            "Memory leak detected! On %s amount of allocated %s memory should be 0, instead it is %"PRIu64,
            whenDesc, allocType( isPersistent ), allocated );

    ELASTICAPM_FORCE_LOG_CRITICAL(
            "Number of allocations not freed: %"PRIu64 ". Following are the first %"PRIu64 " not freed allocation(s)",
            (UInt64)numberOfAllocations, (UInt64)numberOfAllocationsToReport );

    ELASTICAPM_FOR_EACH_INDEX( allocationIndex, numberOfAllocationsToReport)
        reportAllocation( allocationsToReport[ allocationIndex ], allocationIndex, numberOfAllocations );

    #ifdef ELASTICAPM_ON_MEMORY_LEAK_CUSTOM_FUNC
    ELASTICAPM_ON_MEMORY_LEAK_CUSTOM_FUNC();
    #else
    if ( memTracker->abortOnMemoryLeak ) elasticApmAbort();
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
    ELASTICAPM_STATIC_ASSERT( memoryTrackingLevel_not_set == internalChecksLevel_not_set );
    ELASTICAPM_STATIC_ASSERT( numberOfMemoryTrackingLevels <= numberOfInternalChecksLevels );

    ELASTICAPM_ASSERT( ELASTICAPM_IS_IN_INCLUSIVE_RANGE( internalChecksLevel_not_set, internalChecksLevel, internalChecksLevel_all ) );

    if ( internalChecksLevel >= internalChecksLevel_all ) return memoryTrackingLevel_all;
    if ( internalChecksLevel < ( memoryTrackingLevel_all - 1 ) ) return (MemoryTrackingLevel)internalChecksLevel;
    return (MemoryTrackingLevel)( memoryTrackingLevel_all - 1 );
}

String streamMemoryTrackingLevel( MemoryTrackingLevel level, TextOutputStream* txtOutStream )
{
    if ( level == memoryTrackingLevel_not_set )
        return streamStringView( ELASTICAPM_STRING_LITERAL_TO_VIEW( "not_set" ), txtOutStream );

    if ( level >= numberOfMemoryTrackingLevels )
        return streamInt( level, txtOutStream );

    return streamString( memoryTrackingLevelNames[ level ], txtOutStream );
}

#endif // #if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )
