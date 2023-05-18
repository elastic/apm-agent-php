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

#include "elastic_apm_assert.h"


struct IntrusiveDoublyLinkedListNode;
typedef struct IntrusiveDoublyLinkedListNode IntrusiveDoublyLinkedListNode;

struct IntrusiveDoublyLinkedListNode
{
    IntrusiveDoublyLinkedListNode* prev;
    IntrusiveDoublyLinkedListNode* next;
};

struct IntrusiveDoublyLinkedList
{
    IntrusiveDoublyLinkedListNode sentinelHead;
    IntrusiveDoublyLinkedListNode sentinelTail;
};
typedef struct IntrusiveDoublyLinkedList IntrusiveDoublyLinkedList;

static inline
void assertValidLinksIntrusiveDoublyLinkedList( const IntrusiveDoublyLinkedList* list )
{
    for ( const IntrusiveDoublyLinkedListNode* nodeBeforeCurrent = &list->sentinelHead;
          nodeBeforeCurrent != &list->sentinelTail;
          nodeBeforeCurrent = nodeBeforeCurrent->next )
        ELASTIC_APM_ASSERT_EQ_PTR( nodeBeforeCurrent->next->prev, nodeBeforeCurrent );
}

static inline
void assertValidIntrusiveDoublyLinkedList( const IntrusiveDoublyLinkedList* list )
{
    ELASTIC_APM_ASSERT_VALID_PTR( list );

    ELASTIC_APM_ASSERT_PTR_IS_NULL( list->sentinelHead.prev );
    ELASTIC_APM_ASSERT_PTR_IS_NULL( list->sentinelTail.next );

    ELASTIC_APM_ASSERT_VALID_OBJ_O_N( assertValidLinksIntrusiveDoublyLinkedList( list ) );
}

#define ELASTIC_APM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( list ) \
    ELASTIC_APM_ASSERT_VALID_OBJ( assertValidIntrusiveDoublyLinkedList( list ) ) \

static inline
void initIntrusiveDoublyLinkedList( IntrusiveDoublyLinkedList* list )
{
    ELASTIC_APM_ASSERT_VALID_PTR( list );

    list->sentinelHead.prev = NULL;
    list->sentinelHead.next = &list->sentinelTail;
    list->sentinelTail.prev = &list->sentinelHead;
    list->sentinelTail.next = NULL;

    ELASTIC_APM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( list );
}

static inline
void addToIntrusiveDoublyLinkedListBack( IntrusiveDoublyLinkedList* list, IntrusiveDoublyLinkedListNode* newNode )
{
    ELASTIC_APM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( list );
    ELASTIC_APM_ASSERT_VALID_PTR( newNode );

    IntrusiveDoublyLinkedListNode* oldLastNode = list->sentinelTail.prev;

    oldLastNode->next = newNode;
    newNode->prev = oldLastNode;
    newNode->next = &list->sentinelTail;
    list->sentinelTail.prev = newNode;

    ELASTIC_APM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( list );
}

struct IntrusiveDoublyLinkedListIterator
{
    #if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )
    const IntrusiveDoublyLinkedList* list;
    #endif
    const IntrusiveDoublyLinkedListNode* currentNode;
};
typedef struct IntrusiveDoublyLinkedListIterator IntrusiveDoublyLinkedListIterator;

static inline
void assertValidIntrusiveDoublyLinkedListIteratorBelongs( IntrusiveDoublyLinkedListIterator iterator )
{
    const IntrusiveDoublyLinkedListNode* listNode = &iterator.list->sentinelHead;
    do {
        // We do next before comparing against iterator because
        // a valid iterator should not point to sentinelHead
        listNode = listNode->next;
        if ( iterator.currentNode == listNode ) return;
    } while( listNode != &iterator.list->sentinelTail );

    ELASTIC_APM_ASSERT( false, "Iterator doesn't point to any of the nodes." );
}

static inline
void assertValidIntrusiveDoublyLinkedListIterator( IntrusiveDoublyLinkedListIterator iterator )
{
    ELASTIC_APM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( iterator.list );
    ELASTIC_APM_ASSERT_VALID_PTR( iterator.currentNode );
    ELASTIC_APM_ASSERT_VALID_PTR( iterator.currentNode->prev );

    ELASTIC_APM_ASSERT_VALID_OBJ_O_N( assertValidIntrusiveDoublyLinkedListIteratorBelongs( iterator ) );
}

#define ELASTIC_APM_ASSERT_VALID_INTRUSIVE_LINKED_LIST_ITERATOR( iterator ) \
    ELASTIC_APM_ASSERT_VALID_OBJ( assertValidIntrusiveDoublyLinkedListIterator( iterator ) ) \

static inline
IntrusiveDoublyLinkedListIterator nodeToIntrusiveDoublyLinkedListIterator(
        const IntrusiveDoublyLinkedList* list,
        const IntrusiveDoublyLinkedListNode* node )
{
    ELASTIC_APM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( list );

    IntrusiveDoublyLinkedListIterator iterator =
    {
        #if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )
        .list = list,
        #endif
        .currentNode = node
    };

    ELASTIC_APM_ASSERT_VALID_INTRUSIVE_LINKED_LIST_ITERATOR( iterator );
    return iterator;
}

static inline
IntrusiveDoublyLinkedListIterator beginIntrusiveDoublyLinkedListIterator( const IntrusiveDoublyLinkedList* list )
{
    ELASTIC_APM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( list );

    return nodeToIntrusiveDoublyLinkedListIterator( list, list->sentinelHead.next );
}

static inline
bool isEndIntrusiveDoublyLinkedListIterator( IntrusiveDoublyLinkedListIterator iterator )
{
    ELASTIC_APM_ASSERT_VALID_INTRUSIVE_LINKED_LIST_ITERATOR( iterator );

    return iterator.currentNode->next == NULL;
}

static inline
const IntrusiveDoublyLinkedListNode* currentNodeIntrusiveDoublyLinkedList( IntrusiveDoublyLinkedListIterator iterator )
{
    ELASTIC_APM_ASSERT_VALID_INTRUSIVE_LINKED_LIST_ITERATOR( iterator );
    ELASTIC_APM_ASSERT( ! isEndIntrusiveDoublyLinkedListIterator( iterator ), "" );

    return iterator.currentNode;
}

static inline
IntrusiveDoublyLinkedListIterator advanceIntrusiveDoublyLinkedListIterator( IntrusiveDoublyLinkedListIterator iterator )
{
    ELASTIC_APM_ASSERT_VALID_INTRUSIVE_LINKED_LIST_ITERATOR( iterator );
    ELASTIC_APM_ASSERT( ! isEndIntrusiveDoublyLinkedListIterator( iterator ), "" );

    return nodeToIntrusiveDoublyLinkedListIterator( iterator.list, iterator.currentNode->next );
}

#define ELASTIC_APM_FOR_EACH_IN_INTRUSIVE_LINKED_LIST( iteratorVar, list ) \
    for ( IntrusiveDoublyLinkedListIterator iteratorVar = beginIntrusiveDoublyLinkedListIterator( (list) ); \
            ! isEndIntrusiveDoublyLinkedListIterator( (iteratorVar) ); \
            iteratorVar = advanceIntrusiveDoublyLinkedListIterator( (iteratorVar) ) )

static inline
void assertInvalidatedIntrusiveDoublyLinkedListIterator( IntrusiveDoublyLinkedListIterator iterator )
{
    ELASTIC_APM_ASSERT_VALID_PTR( iterator.list );
    ELASTIC_APM_ASSERT_VALID_PTR( iterator.currentNode );
    ELASTIC_APM_ASSERT_PTR_IS_NULL( iterator.currentNode->prev );
    ELASTIC_APM_ASSERT_PTR_IS_NULL( iterator.currentNode->next );
}

#define ELASTIC_APM_ASSERT_INVALIDATED_INTRUSIVE_LINKED_LIST_ITERATOR( iterator ) \
    ELASTIC_APM_ASSERT_VALID_OBJ( assertInvalidatedIntrusiveDoublyLinkedListIterator( iterator ) ) \

static inline
void removeCurrentNodeIntrusiveDoublyLinkedList( IntrusiveDoublyLinkedListIterator iterator )
{
    ELASTIC_APM_ASSERT_VALID_INTRUSIVE_LINKED_LIST_ITERATOR( iterator );
    ELASTIC_APM_ASSERT( ! isEndIntrusiveDoublyLinkedListIterator( iterator ), "" );

    IntrusiveDoublyLinkedListNode* removedNode = (IntrusiveDoublyLinkedListNode*)currentNodeIntrusiveDoublyLinkedList( iterator );

    removedNode->prev->next = removedNode->next;
    removedNode->next->prev = removedNode->prev;

    // Invalidate iterator
    // NOTE: it should make the caller's copy invalid as well - not just the copy on this call stack
    removedNode->next = NULL;
    removedNode->prev = NULL;
    ELASTIC_APM_ASSERT_INVALIDATED_INTRUSIVE_LINKED_LIST_ITERATOR( iterator );
    ELASTIC_APM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( iterator.list );
}

static inline
size_t calcIntrusiveDoublyLinkedListSize( const IntrusiveDoublyLinkedList* list )
{
    ELASTIC_APM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( list );
    size_t size = 0;
    ELASTIC_APM_FOR_EACH_IN_INTRUSIVE_LINKED_LIST( iterator, list ) ++size;
    return size;
}
