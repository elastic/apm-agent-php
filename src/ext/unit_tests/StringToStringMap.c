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

#include "StringToStringMap.h"
#include <string.h>
#include "unit_test_util.h"
#include "elasticapm_alloc.h"
#include "DynamicArray.h"

struct StringToStringMapEntry
{
    String key;
    String value;
};
typedef struct StringToStringMapEntry StringToStringMapEntry;

struct StringToStringMap
{
    DynamicArray entries;
};

#define ELASTICAPM_FOR_EACH_STRING_TO_STRING_MAP_ENTRY( entryPtrVar, map ) \
    ELASTICAPM_FOR_EACH_DYNAMIC_ARRAY_ELEMENT( StringToStringMapEntry, entryPtrVar, &((map)->entries) )

static
void assertValidStringToStringMapEntries( const StringToStringMap* map )
{
    ELASTICAPM_FOR_EACH_STRING_TO_STRING_MAP_ENTRY( entry, map )
        ELASTICAPM_CMOCKA_ASSERT_VALID_PTR( entry->key );
}

static
void assertValidStringToStringMap( const StringToStringMap* map )
{
    ELASTICAPM_CMOCKA_ASSERT_VALID_PTR( map );
    ELASTICAPM_ASSERT_VALID_DYNAMIC_ARRAY( StringToStringMapEntry, &map->entries );

    ELASTICAPM_ASSERT_VALID_OBJ_O_N( assertValidStringToStringMapEntries( map ) );
}

#define ELASTICAPM_CMOCKA_ASSERT_VALID_STRING_TO_STRING_MAP( map ) \
    assertValidStringToStringMap( map )

StringToStringMap* newStringToStringMap()
{
    StringToStringMap* map = NULL;
    ResultCode resultCode;

    ELASTICAPM_PEMALLOC_INSTANCE_IF_FAILED_GOTO( StringToStringMap, map );
    map->entries = ELASTICAPM_MAKE_DYNAMIC_ARRAY( StringToStringMapEntry );

    resultCode = resultSuccess;
    finally:
    ELASTICAPM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( resultCode );
    ELASTICAPM_CMOCKA_ASSERT_VALID_STRING_TO_STRING_MAP( map );
    return map;

    failure:
    goto finally;
}

static
void freeEntry( StringToStringMapEntry* entry )
{
    ELASTICAPM_CMOCKA_ASSERT_VALID_PTR( entry );
    ELASTICAPM_PEFREE_STRING_AND_SET_TO_NULL( strlen( entry->key ) + 1, entry->key );
    ELASTICAPM_PEFREE_STRING_AND_SET_TO_NULL( strlen( entry->value ) + 1, entry->value );
}

void deleteStringToStringMapAndSetToNull( StringToStringMap** pMap )
{
    ELASTICAPM_CMOCKA_ASSERT_VALID_PTR( pMap );

    StringToStringMap* const map = *pMap;
    if ( map == NULL ) return;
    ELASTICAPM_CMOCKA_ASSERT_VALID_STRING_TO_STRING_MAP( map );

    ELASTICAPM_FOR_EACH_STRING_TO_STRING_MAP_ENTRY( entry, map )
        freeEntry( entry );

    ELASTICAPM_DESTRUCT_DYNAMIC_ARRAY( StringToStringMapEntry, &map->entries );

    ELASTICAPM_PEFREE_INSTANCE_AND_SET_TO_NULL( StringToStringMap, *pMap );
}

static
void findEntry( const StringToStringMap* map, String key, StringToStringMapEntry** pEntry, size_t* pIndex )
{
    ELASTICAPM_CMOCKA_ASSERT_VALID_STRING_TO_STRING_MAP( map );

    size_t index = 0;
    ELASTICAPM_FOR_EACH_STRING_TO_STRING_MAP_ENTRY( entry, map )
    {
        if ( areEqualNullableStrings( entry->key, key ) )
        {
            *pIndex = index;
            *pEntry = entry;
            return;
        }
        ++index;
    }

    *pIndex = -1;
    *pEntry = NULL;
}

void setStringToStringMapEntry( StringToStringMap* map, String key, String value )
{
    ELASTICAPM_CMOCKA_ASSERT_VALID_STRING_TO_STRING_MAP( map );
    ELASTICAPM_CMOCKA_ASSERT_VALID_PTR( key );

    ResultCode resultCode;
    String keyDup = NULL;
    String valueDup = NULL;
    StringToStringMapEntry* existingEntry;
    size_t existingEntryIndex;

    if ( value != NULL )
        ELASTICAPM_PEMALLOC_DUP_STRING_IF_FAILED_GOTO( value, valueDup );

    findEntry( map, key, &existingEntry, &existingEntryIndex );
    if ( existingEntry == NULL )
    {
        ELASTICAPM_PEMALLOC_DUP_STRING_IF_FAILED_GOTO( key, keyDup );
        ELASTICAPM_ADD_TO_DYNAMIC_ARRAY_BACK_IF_FAILED_GOTO(
                StringToStringMapEntry,
                &map->entries,
                (&(StringToStringMapEntry){ .key = keyDup, .value = valueDup }) );
    }
    else
    {
        ELASTICAPM_PEFREE_STRING_AND_SET_TO_NULL( strlen( existingEntry->value ) + 1, existingEntry->value );
        existingEntry->value = valueDup;
    }

    resultCode = resultSuccess;

    finally:
    ELASTICAPM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( resultCode );
    ELASTICAPM_CMOCKA_ASSERT_VALID_STRING_TO_STRING_MAP( map );
    return;

    failure:
    ELASTICAPM_PEFREE_STRING_AND_SET_TO_NULL( strlen( keyDup ) + 1, keyDup );
    ELASTICAPM_PEFREE_STRING_AND_SET_TO_NULL( strlen( valueDup ) + 1, valueDup );
    goto finally;
}

void deleteStringToStringMapEntry( StringToStringMap* map, String key )
{
    ELASTICAPM_CMOCKA_ASSERT_VALID_STRING_TO_STRING_MAP( map );
    ELASTICAPM_CMOCKA_ASSERT_VALID_PTR( key );

    StringToStringMapEntry* entry;
    size_t entryIndex;
    findEntry( map, key, &entry, &entryIndex );
    freeEntry( entry );
    ELASTICAPM_REMOVE_DYNAMIC_ARRAY_ELEMENT_AT( StringToStringMapEntry, &map->entries, entryIndex );

    ELASTICAPM_CMOCKA_ASSERT_VALID_STRING_TO_STRING_MAP( map );
}

bool getStringToStringMapEntry( const StringToStringMap* map, String key, String* value )
{
    ELASTICAPM_CMOCKA_ASSERT_VALID_STRING_TO_STRING_MAP( map );
    ELASTICAPM_CMOCKA_ASSERT_VALID_PTR( key );
    ELASTICAPM_CMOCKA_ASSERT_VALID_PTR( value );

    StringToStringMapEntry* entry;
    size_t entryIndex;
    findEntry( map, key, &entry, &entryIndex );

    if ( entry == NULL ) return false;

    *value = entry->value;
    return true;
}