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

#include <stdbool.h>
#include "basic_types.h"
#include "basic_macros.h"

enum ZValueType
{
    zvalType_null,
    zvalType_bool,
    zvalType_long,
    zvalType_string,
    zvalType_double
};
typedef enum ZValueType ZValueType;

union ZValueUnion
{
    bool boolValue;
    long longValue;
    String stringValue;
    double doubleValue;
};
typedef union ZValueUnion ZValueUnion;

struct ZValueStruct
{
    ZValueType type;
    ZValueUnion value;
};
//////////////////////////////////////////////////////////////////////////////
//
// z* types are used in "ConfigManager.h" and "ConfigManager.c"
// via ELASTIC_APM_MOCK_PHP_DEPS defined in unit tests' CMakeLists.txt
//
#ifdef ELASTIC_APM_UNDER_IDE
#   pragma clang diagnostic push
#   pragma ide diagnostic ignored "OCUnusedGlobalDeclarationInspection"
#   pragma clang diagnostic ignored "-Wunknown-pragmas"
#endif

typedef struct ZValueStruct zval;

// We don't use bool from stdbool.h on purpose
// because real "php.h" defines zend_bool as it's done below
typedef unsigned char zend_bool;

char* zend_ini_string_ex( char* name, size_t name_length, int orig, zend_bool* exists );

#ifdef ELASTIC_APM_UNDER_IDE
#   pragma clang diagnostic pop
#endif
//
// z* types are used in "ConfigManager.h" and "ConfigManager.c"
// via ELASTIC_APM_MOCK_PHP_DEPS defined in unit tests' CMakeLists.txt
//
//////////////////////////////////////////////////////////////////////////////


#define ZVAL_ASSIGN_NULL( pZvalArg ) \
    do { \
        (pZvalArg)->type = zvalType_null; \
	} while (0)

#define ZVAL_ASSIGN( pZvalArg, valueTypeArg, valueArg ) \
    do { \
		(pZvalArg)->type = zvalType_##valueTypeArg; \
		(pZvalArg)->value . valueTypeArg##Value = (valueArg); \
	} while (0)

#define RETVAL_NULL()                   ZVAL_ASSIGN_NULL( return_value )
#define RETVAL_BOOL( boolValueArg )     ZVAL_ASSIGN( return_value, bool, boolValueArg )
#define RETVAL_LONG( longValueArg )     ZVAL_ASSIGN( return_value, long, longValueArg )
#define RETVAL_STRING( stringValueArg ) ZVAL_ASSIGN( return_value, string, stringValueArg )
#define RETVAL_DOUBLE( doubleValueArg ) ZVAL_ASSIGN( return_value, double, doubleValueArg )

//////////////////////////////////////////////////////////////////////////////
//
// RETURN_* macros are used in "ConfigManager.c"
// via ELASTIC_APM_MOCK_PHP_DEPS defined in unit tests' CMakeLists.txt
//
#ifdef ELASTIC_APM_UNDER_IDE
#   pragma clang diagnostic push
#   pragma clang diagnostic ignored "-Wunknown-pragmas"
#   pragma ide diagnostic ignored "OCUnusedMacroInspection"
#endif

// We don't use "do {} while (0)" enclosing on purpose
// because real "php.h" defines these macros as it's done below - just enclosing in "{}"
#define RETURN_NULL()                   { RETVAL_NULL(); return; }
#define RETURN_BOOL( boolValueArg )     { RETVAL_BOOL( boolValueArg ); return; }
#define RETURN_LONG( longValueArg )     { RETVAL_LONG( longValueArg ); return; }
#define RETURN_STRING( stringValueArg ) { RETVAL_STRING( stringValueArg ); return; }
#define RETURN_DOUBLE( doubleValueArg ) { RETVAL_DOUBLE( doubleValueArg ); return; }

#ifdef ELASTIC_APM_UNDER_IDE
#   pragma clang diagnostic pop
#endif
//
// RETURN_* macros are used in "ConfigManager.c"
// via ELASTIC_APM_MOCK_PHP_DEPS defined in unit tests' CMakeLists.txt
//
//////////////////////////////////////////////////////////////////////////////
