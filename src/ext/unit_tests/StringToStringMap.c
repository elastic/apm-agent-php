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

#include "StringToStringMap.h"
#include <string.h>
#include "unit_test_util.h"
#include "elastic_apm_alloc.h"
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

#define ELASTIC_APM_FOR_EACH_STRING_TO_STRING_MAP_ENTRY( entryPtrVar, map ) \
    ELASTIC_APM_FOR_EACH_DYNAMIC_ARRAY_ELEMENT( StringToStringMapEntry, entryPtrVar, &((map)->entries) )

static
void assertValidStringToStringMapEntries( const StringToStringMap* map )
{
    ELASTIC_APM_FOR_EACH_STRING_TO_STRING_MAP_ENTRY( entry, map )
        ELASTIC_APM_CMOCKA_ASSERT_VALID_PTR( entry->key );
}

static
void assertValidStringToStringMap( const StringToStringMap* map )
{
    ELASTIC_APM_CMOCKA_ASSERT_VALID_PTR( map );
    ELASTIC_APM_ASSERT_VALID_DYNAMIC_ARRAY( StringToStringMapEntry, &map->entries );

    ELASTIC_APM_ASSERT_VALID_OBJ_O_N( assertValidStringToStringMapEntries( map ) );
}

#define ELASTIC_APM_CMOCKA_ASSERT_VALID_STRING_TO_STRING_MAP( map ) \
    assertValidStringToStringMap( map )

StringToStringMap* newStringToStringMap()
{
    StringToStringMap* map = NULL;
    ResultCode resultCode;

    ELASTIC_APM_PEMALLOC_INSTANCE_IF_FAILED_GOTO( StringToStringMap, map );
    map->entries = ELASTIC_APM_MAKE_DYNAMIC_ARRAY( StringToStringMapEntry );

    resultCode = resultSuccess;
    finally:
    ELASTIC_APM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( resultCode );
    ELASTIC_APM_CMOCKA_ASSERT_VALID_STRING_TO_STRING_MAP( map );
    return map;

    failure:
    goto finally;
}

static
void freeEntry( StringToStringMapEntry* entry )
{
    ELASTIC_APM_CMOCKA_ASSERT_VALID_PTR( entry );
    ELASTIC_APM_PEFREE_STRING_AND_SET_TO_NULL( entry->key );
    ELASTIC_APM_PEFREE_STRING_AND_SET_TO_NULL( entry->value );
}

void deleteStringToStringMapAndSetToNull( StringToStringMap** pMap )
{
    ELASTIC_APM_CMOCKA_ASSERT_VALID_PTR( pMap );

    StringToStringMap* const map = *pMap;
    if ( map == NULL ) return;
    ELASTIC_APM_CMOCKA_ASSERT_VALID_STRING_TO_STRING_MAP( map );

    ELASTIC_APM_FOR_EACH_STRING_TO_STRING_MAP_ENTRY( entry, map )
        freeEntry( entry );

    ELASTIC_APM_DESTRUCT_DYNAMIC_ARRAY( StringToStringMapEntry, &map->entries );

    ELASTIC_APM_PEFREE_INSTANCE_AND_SET_TO_NULL( StringToStringMap, *pMap );
}

static
void findEntry( const StringToStringMap* map, String key, StringToStringMapEntry** pEntry, size_t* pIndex )
{
    ELASTIC_APM_CMOCKA_ASSERT_VALID_STRING_TO_STRING_MAP( map );

    size_t index = 0;
    ELASTIC_APM_FOR_EACH_STRING_TO_STRING_MAP_ENTRY( entry, map )
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
    ELASTIC_APM_CMOCKA_ASSERT_VALID_STRING_TO_STRING_MAP( map );
    ELASTIC_APM_CMOCKA_ASSERT_VALID_PTR( key );

    ResultCode resultCode;
    String keyDup = NULL;
    String valueDup = NULL;
    StringToStringMapEntry* existingEntry;
    size_t existingEntryIndex;

    if ( value != NULL )
        ELASTIC_APM_PEMALLOC_DUP_STRING_IF_FAILED_GOTO( value, valueDup );

    findEntry( map, key, &existingEntry, &existingEntryIndex );
    if ( existingEntry == NULL )
    {
        ELASTIC_APM_PEMALLOC_DUP_STRING_IF_FAILED_GOTO( key, keyDup );
        ELASTIC_APM_ADD_TO_DYNAMIC_ARRAY_BACK_IF_FAILED_GOTO(
                StringToStringMapEntry,
                &map->entries,
                (&(StringToStringMapEntry){ .key = keyDup, .value = valueDup }) );
    }
    else
    {
        ELASTIC_APM_PEFREE_STRING_AND_SET_TO_NULL( existingEntry->value );
        existingEntry->value = valueDup;
    }

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( resultCode );
    ELASTIC_APM_CMOCKA_ASSERT_VALID_STRING_TO_STRING_MAP( map );
    return;

    failure:
    ELASTIC_APM_PEFREE_STRING_AND_SET_TO_NULL( keyDup );
    ELASTIC_APM_PEFREE_STRING_AND_SET_TO_NULL( valueDup );
    goto finally;
}

void deleteStringToStringMapEntry( StringToStringMap* map, String key )
{
    ELASTIC_APM_CMOCKA_ASSERT_VALID_STRING_TO_STRING_MAP( map );
    ELASTIC_APM_CMOCKA_ASSERT_VALID_PTR( key );

    StringToStringMapEntry* entry;
    size_t entryIndex;
    findEntry( map, key, &entry, &entryIndex );
    freeEntry( entry );
    ELASTIC_APM_REMOVE_DYNAMIC_ARRAY_ELEMENT_AT( StringToStringMapEntry, &map->entries, entryIndex );

    ELASTIC_APM_CMOCKA_ASSERT_VALID_STRING_TO_STRING_MAP( map );
}

bool getStringToStringMapEntry( const StringToStringMap* map, String key, String* value )
{
    ELASTIC_APM_CMOCKA_ASSERT_VALID_STRING_TO_STRING_MAP( map );
    ELASTIC_APM_CMOCKA_ASSERT_VALID_PTR( key );
    ELASTIC_APM_CMOCKA_ASSERT_VALID_PTR( value );

    StringToStringMapEntry* entry;
    size_t entryIndex;
    findEntry( map, key, &entry, &entryIndex );

    if ( entry == NULL ) return false;

    *value = entry->value;
    return true;
}