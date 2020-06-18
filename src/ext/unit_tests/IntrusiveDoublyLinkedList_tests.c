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

#include "cmocka_wrapped_for_unit_tests.h"
#include <stddef.h>
#include "unit_test_util.h"
#include "IntrusiveDoublyLinkedList.h"

static
void empty_list( void** testFixtureState )
{
    ELASTICAPM_UNUSED( testFixtureState );

    IntrusiveDoublyLinkedList list;
    initIntrusiveDoublyLinkedList( &list );
    assertValidIntrusiveDoublyLinkedList( &list );
    IntrusiveDoublyLinkedListIterator iterator = beginIntrusiveDoublyLinkedListIterator( &list );
    assertValidIntrusiveDoublyLinkedListIterator( iterator );
    ELASTICAPM_CMOCKA_ASSERT( isEndIntrusiveDoublyLinkedListIterator( iterator ) );
}

struct MyTestStruct
{
    int payload;
    IntrusiveDoublyLinkedListNode intrusiveNode;
};
typedef struct MyTestStruct MyTestStruct;

static
const MyTestStruct* fromNodeToMyTestStruct( const IntrusiveDoublyLinkedListNode* intrusiveListNode )
{
    return (const MyTestStruct*)( ((const Byte*) intrusiveListNode ) - offsetof( MyTestStruct, intrusiveNode ) );
}

static
void verifyListContent( const IntrusiveDoublyLinkedList* list, int* expectedElements, size_t expectedNumberOfElements )
{
    assertValidIntrusiveDoublyLinkedList( list );

    size_t expectedElementsIndex = 0;
    ELASTICAPM_FOR_EACH_IN_INTRUSIVE_LINKED_LIST( iterator, list )
    {
        ELASTICAPM_CMOCKA_ASSERT( expectedElementsIndex < expectedNumberOfElements );

        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL(
                fromNodeToMyTestStruct( currentNodeIntrusiveDoublyLinkedList( iterator ) )->payload,
                expectedElements[ expectedElementsIndex ] );

        ++expectedElementsIndex;
    }
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( expectedElementsIndex, expectedNumberOfElements );

    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( calcIntrusiveDoublyLinkedListSize( list ), expectedNumberOfElements );
}

static
void multiplyEachListElement( IntrusiveDoublyLinkedList* list, int factor )
{
    assertValidIntrusiveDoublyLinkedList( list );

    ELASTICAPM_FOR_EACH_IN_INTRUSIVE_LINKED_LIST( iterator, list )
    {
        MyTestStruct* myTestStruct = (MyTestStruct*) fromNodeToMyTestStruct( currentNodeIntrusiveDoublyLinkedList( iterator ) );
        myTestStruct->payload *= factor;
    }
}
static
IntrusiveDoublyLinkedListIterator findByPayload( const IntrusiveDoublyLinkedList* list, int payloadToFind )
{
    IntrusiveDoublyLinkedListIterator iterator = beginIntrusiveDoublyLinkedListIterator( list );

    for ( ; ! isEndIntrusiveDoublyLinkedListIterator( iterator ); iterator = advanceIntrusiveDoublyLinkedListIterator( iterator ) )
        if ( fromNodeToMyTestStruct( currentNodeIntrusiveDoublyLinkedList( iterator ) )->payload == payloadToFind )
            break;

    if ( ! isEndIntrusiveDoublyLinkedListIterator( iterator ) )
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( fromNodeToMyTestStruct( currentNodeIntrusiveDoublyLinkedList( iterator ) )->payload, payloadToFind );

    return iterator;
}

static
void various_operations( void** testFixtureState )
{
    ELASTICAPM_UNUSED( testFixtureState );

    IntrusiveDoublyLinkedList list;
    initIntrusiveDoublyLinkedList( &list );
    assertValidIntrusiveDoublyLinkedList( &list );
    MyTestStruct myTestStruct1 = { .payload = 1 };
    addToIntrusiveDoublyLinkedListBack( &list, &myTestStruct1.intrusiveNode );
    {
        int expectedElements[] = { 1 };
        verifyListContent( &list, expectedElements, ELASTICAPM_STATIC_ARRAY_SIZE( expectedElements ) );
    }
    MyTestStruct myTestStruct2 = { .payload = 2 };
    addToIntrusiveDoublyLinkedListBack( &list, &myTestStruct2.intrusiveNode );
    {
        int expectedElements[] = { 1, 2 };
        verifyListContent( &list, expectedElements, ELASTICAPM_STATIC_ARRAY_SIZE( expectedElements ) );
    }
    MyTestStruct myTestStruct3 = { .payload = 3 };
    addToIntrusiveDoublyLinkedListBack( &list, &myTestStruct3.intrusiveNode );
    {
        int expectedElements[] = { 1, 2, 3 };
        verifyListContent( &list, expectedElements, ELASTICAPM_STATIC_ARRAY_SIZE( expectedElements ) );
    }
    MyTestStruct myTestStruct4 = { .payload = 4 };
    addToIntrusiveDoublyLinkedListBack( &list, &myTestStruct4.intrusiveNode );
    {
        int expectedElements[] = { 1, 2, 3, 4 };
        verifyListContent( &list, expectedElements, ELASTICAPM_STATIC_ARRAY_SIZE( expectedElements ) );
    }

    multiplyEachListElement( &list, 5 );
    {
        int expectedElements[] = { 1 *5, 2 *5, 3 *5, 4 *5 };
        verifyListContent( &list, expectedElements, ELASTICAPM_STATIC_ARRAY_SIZE( expectedElements ) );
    }
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( myTestStruct1.payload, 1 *5 );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( myTestStruct2.payload, 2 *5 );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( myTestStruct3.payload, 3 *5 );
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( myTestStruct4.payload, 4 *5 );

    ELASTICAPM_CMOCKA_ASSERT( isEndIntrusiveDoublyLinkedListIterator( findByPayload( &list, 123 ) ) );

    IntrusiveDoublyLinkedListIterator iterator1 = findByPayload( &list, 1 * 5 );
    ELASTICAPM_CMOCKA_ASSERT( ! isEndIntrusiveDoublyLinkedListIterator( iterator1 ) );
    IntrusiveDoublyLinkedListIterator iterator2 = findByPayload( &list, 2 * 5 );
    ELASTICAPM_CMOCKA_ASSERT( ! isEndIntrusiveDoublyLinkedListIterator( iterator2 ) );
    IntrusiveDoublyLinkedListIterator iterator3 = nodeToIntrusiveDoublyLinkedListIterator( &list, &myTestStruct3.intrusiveNode );
    ELASTICAPM_CMOCKA_ASSERT( ! isEndIntrusiveDoublyLinkedListIterator( iterator3 ) );
    IntrusiveDoublyLinkedListIterator iterator4 = findByPayload( &list, 4 * 5 );
    ELASTICAPM_CMOCKA_ASSERT( ! isEndIntrusiveDoublyLinkedListIterator( iterator4 ) );
    IntrusiveDoublyLinkedListIterator iteratorEnd = advanceIntrusiveDoublyLinkedListIterator( iterator4 );
    ELASTICAPM_CMOCKA_ASSERT( isEndIntrusiveDoublyLinkedListIterator( iteratorEnd ) );

    // Remove the last element: { 1 *5, 2 *5, 3 *5, 4 *5 } => { 1 *5, 2 *5, 3 *5 }
    //                                              ^^^^
    {
        removeCurrentNodeIntrusiveDoublyLinkedList( iterator4 );

        assertInvalidatedIntrusiveDoublyLinkedListIterator( iterator4 );

        // Verify that neighbouring node(s) are not affected by remove
        ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST_ITERATOR( iterator3 );
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( fromNodeToMyTestStruct( currentNodeIntrusiveDoublyLinkedList( iterator3 ) )->payload, 3 * 5 );
        ELASTICAPM_CMOCKA_ASSERT( isEndIntrusiveDoublyLinkedListIterator( iteratorEnd ) );

        // Verify new expected list's content
        int expectedElements[] = { 1 *5, 2 *5, 3 *5 };
        verifyListContent( &list, expectedElements, ELASTICAPM_STATIC_ARRAY_SIZE( expectedElements ) );

        // Verify that removed value should not be found
        ELASTICAPM_CMOCKA_ASSERT( isEndIntrusiveDoublyLinkedListIterator( findByPayload( &list, 4 *5 ) ) );

        // Verify that other fields of the removed struct are not affected
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( myTestStruct4.payload, 4 *5 );
    }

    // Remove an element in the middle: { 1 *5, 2 *5, 3 *5 } => { 1 *5, 3 *5 }
    //                                          ^^^^
    {
        removeCurrentNodeIntrusiveDoublyLinkedList( iterator2 );

        ELASTICAPM_ASSERT_INVALIDATED_INTRUSIVE_LINKED_LIST_ITERATOR( iterator2 );

        // Verify that neighbouring node(s) are not affected by remove
        ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST_ITERATOR( iterator1 );
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( fromNodeToMyTestStruct( currentNodeIntrusiveDoublyLinkedList( iterator1 ) )->payload, 1 * 5 );
        ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST_ITERATOR( iterator3 );
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( fromNodeToMyTestStruct( currentNodeIntrusiveDoublyLinkedList( iterator3 ) )->payload, 3 * 5 );

        // Verify new expected list's content
        int expectedElements[] = { 1 *5, 3 *5 };
        verifyListContent( &list, expectedElements, ELASTICAPM_STATIC_ARRAY_SIZE( expectedElements ) );

        // Verify that removed value should not be found
        ELASTICAPM_CMOCKA_ASSERT( isEndIntrusiveDoublyLinkedListIterator( findByPayload( &list, 2 *5 ) ) );

        // Verify that other fields of the removed struct are not affected
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( myTestStruct3.payload, 3 *5 );
    }

    // Remove the first element: { 1 *5, 3 *5 } => { 3 *5 }
    //                             ^^^^
    {
        removeCurrentNodeIntrusiveDoublyLinkedList( iterator1 );

        ELASTICAPM_ASSERT_INVALIDATED_INTRUSIVE_LINKED_LIST_ITERATOR( iterator1 );

        // Verify that neighbouring node(s) are not affected by remove
        ELASTICAPM_ASSERT_VALID_INTRUSIVE_LINKED_LIST_ITERATOR( iterator3 );
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( fromNodeToMyTestStruct( currentNodeIntrusiveDoublyLinkedList( iterator3 ) )->payload, 3 * 5 );

        // Verify new expected list's content
        int expectedElements[] = { 3 *5 };
        verifyListContent( &list, expectedElements, ELASTICAPM_STATIC_ARRAY_SIZE( expectedElements ) );

        // Verify that removed value should not be found
        ELASTICAPM_CMOCKA_ASSERT( isEndIntrusiveDoublyLinkedListIterator( findByPayload( &list, 1 *5 ) ) );

        // Verify that other fields of the removed struct are not affected
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( myTestStruct1.payload, 1 *5 );
    }

    // Remove the only element: { 3 *5 } => {}
    //                            ^^^^
    {
        removeCurrentNodeIntrusiveDoublyLinkedList( iterator3 );

        // Verify that neighbouring node(s) are not affected by remove
        ELASTICAPM_ASSERT_INVALIDATED_INTRUSIVE_LINKED_LIST_ITERATOR( iterator3 );
        ELASTICAPM_CMOCKA_ASSERT( isEndIntrusiveDoublyLinkedListIterator( iteratorEnd ) );

        // Verify new expected list's content
        verifyListContent( &list, NULL, 0 );

        // Verify that removed value should not be found
        ELASTICAPM_CMOCKA_ASSERT( isEndIntrusiveDoublyLinkedListIterator( findByPayload( &list, 3 *5 ) ) );

        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( myTestStruct3.payload, 3 *5 );
    }
}

int run_IntrusiveDoublyLinkedList_tests()
{
    const struct CMUnitTest tests [] =
    {
            ELASTICAPM_CMOCKA_UNIT_TEST( empty_list ),
        ELASTICAPM_CMOCKA_UNIT_TEST( various_operations ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
