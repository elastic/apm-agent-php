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

#include <algorithm>
#include <stdbool.h>
#include "basic_types.h"
#include "StringView.h"
#include "basic_macros.h"
#include "IntrusiveDoublyLinkedList.h"
#include "internal_checks.h"
#include "TextOutputStream_forward_decl.h"

#ifndef ELASTIC_APM_MEMORY_TRACKING_ENABLED_01
#   if defined( ELASTIC_APM_MEMORY_TRACKING_ENABLED ) && ( ELASTIC_APM_MEMORY_TRACKING_ENABLED == 0 )
#       define ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 0
#   else
#       define ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 1
#   endif
#endif

#if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )

enum MemoryTrackingLevel
{
    memoryTrackingLevel_not_set = -1,
    memoryTrackingLevel_off = 0,

    memoryTrackingLevel_totalCountOnly,
    memoryTrackingLevel_eachAllocation,
    memoryTrackingLevel_eachAllocationWithStackTrace,

    memoryTrackingLevel_all,
    numberOfMemoryTrackingLevels = memoryTrackingLevel_all + 1
};
typedef enum MemoryTrackingLevel MemoryTrackingLevel;

#define ELASTIC_APM_ASSERT_VALID_MEMORY_TRACKING_LEVEL( level ) \
    ELASTIC_APM_ASSERT( ELASTIC_APM_IS_IN_END_EXCLUDED_RANGE( memoryTrackingLevel_not_set, (level), numberOfMemoryTrackingLevels ) \
        , #level ": %u", (unsigned int)(level) ) \
/**/

extern const char* memoryTrackingLevelNames[ numberOfMemoryTrackingLevels ];

#ifndef ELASTIC_APM_MEMORY_TRACKING_DEFAULT_LEVEL
#   if ( ELASTIC_APM_IS_DEBUG_BUILD_01 != 0 )
#       define ELASTIC_APM_MEMORY_TRACKING_DEFAULT_LEVEL memoryTrackingLevel_all
#   else
#       define ELASTIC_APM_MEMORY_TRACKING_DEFAULT_LEVEL memoryTrackingLevel_off
#   endif
#endif

#ifndef ELASTIC_APM_MEMORY_TRACKING_DEFAULT_ABORT_ON_MEMORY_LEAK
#   if ( ELASTIC_APM_IS_DEBUG_BUILD_01 != 0 )
#       define ELASTIC_APM_MEMORY_TRACKING_DEFAULT_ABORT_ON_MEMORY_LEAK true
#   else
#       define ELASTIC_APM_MEMORY_TRACKING_DEFAULT_ABORT_ON_MEMORY_LEAK false
#   endif
#endif

MemoryTrackingLevel internalChecksToMemoryTrackingLevel( InternalChecksLevel internalChecksLevel );

struct MemoryTracker
{
    MemoryTrackingLevel level;
    bool abortOnMemoryLeak;

    UInt64 allocatedPersistent;
    IntrusiveDoublyLinkedList allocatedPersistentBlocks;
    UInt64 allocatedRequestScoped;
    IntrusiveDoublyLinkedList allocatedRequestScopedBlocks;
};
typedef struct MemoryTracker MemoryTracker;

static inline
void assertValidMemoryTracker( MemoryTracker* memTracker )
{
    ELASTIC_APM_ASSERT_VALID_PTR( memTracker );
    ELASTIC_APM_ASSERT_VALID_MEMORY_TRACKING_LEVEL( memTracker->level );
    ELASTIC_APM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( &memTracker->allocatedPersistentBlocks );
    ELASTIC_APM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( &memTracker->allocatedRequestScopedBlocks );
}

#define ELASTIC_APM_ASSERT_VALID_MEMORY_TRACKER( memTracker ) \
    ELASTIC_APM_ASSERT_VALID_OBJ( assertValidMemoryTracker( memTracker ) ) \

static inline
bool isMemoryTrackingEnabled( MemoryTracker* memTracker )
{
    return memTracker->level > memoryTrackingLevel_off;
}

static inline
bool shouldCaptureStackTrace( MemoryTracker* memTracker )
{
    return memTracker->level > memoryTrackingLevel_eachAllocationWithStackTrace;
}

static inline
void reconfigureMemoryTracker(
        MemoryTracker* memTracker,
        MemoryTrackingLevel newConfiguredLevel,
        bool newConfiguredAbortOnMemoryLeak )
{
    ELASTIC_APM_ASSERT_VALID_MEMORY_TRACKING_LEVEL( newConfiguredLevel );
    ELASTIC_APM_ASSERT( newConfiguredLevel != memoryTrackingLevel_not_set, "" );

    /// We cannot increase tacking level after the start because it's possible that some allocations were already made
    /// so starting tracking with higher level after some allocations were already made will produce invalid results
    memTracker->level = std::min( memTracker->level, newConfiguredLevel );

    memTracker->abortOnMemoryLeak = newConfiguredAbortOnMemoryLeak;
}

void constructMemoryTracker( MemoryTracker* memTracker );
void memoryTrackerRequestInit( MemoryTracker* memTracker );
size_t memoryTrackerCalcSizeToAlloc(
        MemoryTracker* memTracker,
        size_t originallyRequestedSize,
        size_t stackTraceAddressesCount );
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
        size_t stackTraceAddressesCount );
void memoryTrackerBeforeFree(
        MemoryTracker* memTracker,
        const void* allocatedBlock,
        size_t originallyRequestedSize,
        bool isPersistent,
        size_t* possibleActuallyRequestedSize );
void memoryTrackerRequestShutdown( MemoryTracker* memTracker );
void destructMemoryTracker( MemoryTracker* memTracker );

String streamMemoryTrackingLevel( MemoryTrackingLevel level, TextOutputStream* txtOutStream );

MemoryTracker* getGlobalMemoryTracker();

#endif // #if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )
