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

#include "mock_php_ini.h"
#include "mock_php.h"
#include "basic_macros.h" // ELASTICAPM_UNUSED
#include "unit_test_util.h"

static bool g_isMockPhpIniInited = false;
//static StringToStringMap* g_mockPhpIniMap = NULL;

void initMockPhpIni()
{
    ELASTICAPM_CMOCKA_ASSERT( ! g_isMockPhpIniInited );
    g_isMockPhpIniInited = true;
}

void uninitMockPhpIni()
{
    ELASTICAPM_CMOCKA_ASSERT( g_isMockPhpIniInited );
    g_isMockPhpIniInited = false;
}

char* zend_ini_string_ex( char* name, size_t name_length, int orig, zend_bool* exists )
{
    ELASTICAPM_ASSERT_VALID_PTR( name );
    ELASTICAPM_UNUSED( orig );

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

    ELASTICAPM_UNUSED( name_length );

    *exists = 0;
    return NULL;
}
