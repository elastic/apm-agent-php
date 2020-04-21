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

#include "elasticapm_assert.h"


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
        ELASTICAPM_ASSERT( nodeBeforeCurrent->next->prev == nodeBeforeCurrent );
}

static inline
void assertValidIntrusiveDoublyLinkedList( const IntrusiveDoublyLinkedList* list )
{
    ELASTICAPM_ASSERT_VALID_PTR( list );

    ELASTICAPM_ASSERT( list->sentinelHead.prev == NULL );
    ELASTICAPM_ASSERT( list->sentinelTail.next == NULL );

    ELASTICAPM_ASSERT_VALID_OBJ_O_N( assertValidLinksIntrusiveDoublyLinkedList( list ) );
}

#define ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( list ) \
    ELASTICAPM_ASSERT_VALID_OBJ( assertValidIntrusiveDoublyLinkedList( list ) ) \

static inline
void initIntrusiveDoublyLinkedList( IntrusiveDoublyLinkedList* list )
{
    ELASTICAPM_ASSERT_VALID_PTR( list );

    list->sentinelHead.prev = NULL;
    list->sentinelHead.next = &list->sentinelTail;
    list->sentinelTail.prev = &list->sentinelHead;
    list->sentinelTail.next = NULL;

    ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( list );
}

static inline
void addToIntrusiveDoublyLinkedListBack( IntrusiveDoublyLinkedList* list, IntrusiveDoublyLinkedListNode* newNode )
{
    ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( list );
    ELASTICAPM_ASSERT_VALID_PTR( newNode );

    IntrusiveDoublyLinkedListNode* oldLastNode = list->sentinelTail.prev;

    oldLastNode->next = newNode;
    newNode->prev = oldLastNode;
    newNode->next = &list->sentinelTail;
    list->sentinelTail.prev = newNode;

    ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( list );
}

struct IntrusiveDoublyLinkedListIterator
{
    #if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )
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

    ELASTICAPM_ASSERT( false, "Iterator doesn't point to any of the nodes." );
}

static inline
void assertValidIntrusiveDoublyLinkedListIterator( IntrusiveDoublyLinkedListIterator iterator )
{
    ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( iterator.list );
    ELASTICAPM_ASSERT_VALID_PTR( iterator.currentNode );
    ELASTICAPM_ASSERT_VALID_PTR( iterator.currentNode->prev );

    ELASTICAPM_ASSERT_VALID_OBJ_O_N( assertValidIntrusiveDoublyLinkedListIteratorBelongs( iterator ) );
}

#define ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST_ITERATOR( iterator ) \
    ELASTICAPM_ASSERT_VALID_OBJ( assertValidIntrusiveDoublyLinkedListIterator( iterator ) ) \

static inline
IntrusiveDoublyLinkedListIterator nodeToIntrusiveDoublyLinkedListIterator(
        const IntrusiveDoublyLinkedList* list,
        const IntrusiveDoublyLinkedListNode* node )
{
    ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( list );

    IntrusiveDoublyLinkedListIterator iterator =
    {
        #if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )
        .list = list,
        #endif
        .currentNode = node
    };

    ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST_ITERATOR( iterator );
    return iterator;
}

static inline
IntrusiveDoublyLinkedListIterator beginIntrusiveDoublyLinkedListIterator( const IntrusiveDoublyLinkedList* list )
{
    ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( list );

    return nodeToIntrusiveDoublyLinkedListIterator( list, list->sentinelHead.next );
}

static inline
bool isEndIntrusiveDoublyLinkedListIterator( IntrusiveDoublyLinkedListIterator iterator )
{
    ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST_ITERATOR( iterator );

    return iterator.currentNode->next == NULL;
}

static inline
const IntrusiveDoublyLinkedListNode* currentNodeIntrusiveDoublyLinkedList( IntrusiveDoublyLinkedListIterator iterator )
{
    ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST_ITERATOR( iterator );
    ELASTICAPM_ASSERT( ! isEndIntrusiveDoublyLinkedListIterator( iterator ) );

    return iterator.currentNode;
}

static inline
IntrusiveDoublyLinkedListIterator advanceIntrusiveDoublyLinkedListIterator( IntrusiveDoublyLinkedListIterator iterator )
{
    ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST_ITERATOR( iterator );
    ELASTICAPM_ASSERT( ! isEndIntrusiveDoublyLinkedListIterator( iterator ) );

    return nodeToIntrusiveDoublyLinkedListIterator( iterator.list, iterator.currentNode->next );
}

#define ELASTICAPM_FOR_EACH_IN_INTRUSIVE_LINKED_LIST( iteratorVar, list ) \
    for ( IntrusiveDoublyLinkedListIterator iteratorVar = beginIntrusiveDoublyLinkedListIterator( (list) ); \
            ! isEndIntrusiveDoublyLinkedListIterator( (iteratorVar) ); \
            iteratorVar = advanceIntrusiveDoublyLinkedListIterator( (iteratorVar) ) )

static inline
void assertInvalidatedIntrusiveDoublyLinkedListIterator( IntrusiveDoublyLinkedListIterator iterator )
{
    ELASTICAPM_ASSERT_VALID_PTR( iterator.list );
    ELASTICAPM_ASSERT_VALID_PTR( iterator.currentNode );
    ELASTICAPM_ASSERT( iterator.currentNode->prev == NULL );
    ELASTICAPM_ASSERT( iterator.currentNode->next == NULL );
}

#define ELASTICAPM_ASSERT_INVALIDATED_INTRUSIVE_LINKED_LIST_ITERATOR( iterator ) \
    ELASTICAPM_ASSERT_VALID_OBJ( assertInvalidatedIntrusiveDoublyLinkedListIterator( iterator ) ) \

static inline
void removeCurrentNodeIntrusiveDoublyLinkedList( IntrusiveDoublyLinkedListIterator iterator )
{
    ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST_ITERATOR( iterator );
    ELASTICAPM_ASSERT( ! isEndIntrusiveDoublyLinkedListIterator( iterator ) );

    IntrusiveDoublyLinkedListNode* removedNode = (IntrusiveDoublyLinkedListNode*)currentNodeIntrusiveDoublyLinkedList( iterator );

    removedNode->prev->next = removedNode->next;
    removedNode->next->prev = removedNode->prev;

    // Invalidate iterator
    // NOTE: it should make the caller's copy invalid as well - not just the copy on this call stack
    removedNode->next = NULL;
    removedNode->prev = NULL;
    ELASTICAPM_ASSERT_INVALIDATED_INTRUSIVE_LINKED_LIST_ITERATOR( iterator );
    ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( iterator.list );
}

static inline
size_t calcIntrusiveDoublyLinkedListSize( const IntrusiveDoublyLinkedList* list )
{
    ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST( list );
    size_t size = 0;
    ELASTICAPM_FOR_EACH_IN_INTRUSIVE_LINKED_LIST( iterator, list ) ++size;
    return size;
}
