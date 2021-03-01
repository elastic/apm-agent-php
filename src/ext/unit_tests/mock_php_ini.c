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

#include "mock_php_ini.h"
#include "mock_php.h"
#include "basic_macros.h" // ELASTIC_APM_UNUSED
#include "unit_test_util.h"

static bool g_isMockPhpIniInited = false;
//static StringToStringMap* g_mockPhpIniMap = NULL;

void initMockPhpIni()
{
    ELASTIC_APM_CMOCKA_ASSERT( ! g_isMockPhpIniInited );
    g_isMockPhpIniInited = true;
}

void uninitMockPhpIni()
{
    ELASTIC_APM_CMOCKA_ASSERT( g_isMockPhpIniInited );
    g_isMockPhpIniInited = false;
}

char* zend_ini_string_ex( char* name, size_t name_length, int orig, zend_bool* exists )
{
    ELASTIC_APM_ASSERT_VALID_PTR( name );
    ELASTIC_APM_UNUSED( orig );

//    assertValidStringToStringMap();
//
//    StringToStringMapEntry* entry = findEntry( makeStringView( name, name_length ) );
//    if ( entry == NULL )
//    {
//        *exists = 0;
//        return NULL;
//    }
//
//    return (char*)entry->value;

    ELASTIC_APM_UNUSED( name_length );

    *exists = 0;
    return NULL;
}
