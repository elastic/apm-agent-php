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

#include <stdbool.h>
#include "basic_types.h"

enum ZValueType
{
    zvalType_null,
    zvalType_bool,
    zvalType_long,
    zvalType_string
};
typedef enum ZValueType ZValueType;

union ZValueUnion
{
    bool boolValue;
    long longValue;
    String stringValue;
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
// via ELASTICAPM_MOCK_PHP_DEPS defined in unit tests' CMakeLists.txt
//
#ifdef ELASTICAPM_UNDER_IDE
#   pragma clang diagnostic push
#   pragma ide diagnostic ignored "OCUnusedGlobalDeclarationInspection"
#   pragma clang diagnostic ignored "-Wunknown-pragmas"
#endif

typedef struct ZValueStruct zval;

// We don't use bool from stdbool.h on purpose
// because real "php.h" defines zend_bool as it's done below
typedef unsigned char zend_bool;

char* zend_ini_string_ex( char* name, size_t name_length, int orig, zend_bool* exists );

#ifdef ELASTICAPM_UNDER_IDE
#   pragma clang diagnostic pop
#endif
//
// z* types are used in "ConfigManager.h" and "ConfigManager.c"
// via ELASTICAPM_MOCK_PHP_DEPS defined in unit tests' CMakeLists.txt
//
//////////////////////////////////////////////////////////////////////////////


#define ZVAL_NULL( pZvalArg ) \
    do { \
        (pZvalArg)->type = zvalType_null; \
	} while (0)

#define ZVAL_BOOL( pZvalArg, boolValueArg ) \
    do { \
		(pZvalArg)->type = zvalType_bool; \
		(pZvalArg)->value.boolValue = (boolValueArg); \
	} while (0)

#define ZVAL_LONG( pZvalArg, longValueArg ) \
    do { \
		(pZvalArg)->type = zvalType_long; \
		(pZvalArg)->value.longValue = (longValueArg); \
	} while (0)

#define ZVAL_STRING( pZvalArg, stringValueArg ) \
    do { \
		(pZvalArg)->type = zvalType_string; \
		(pZvalArg)->value.stringValue = (stringValueArg); \
	} while (0)

#define RETVAL_NULL()                   ZVAL_NULL( return_value )
#define RETVAL_BOOL( boolValueArg )     ZVAL_BOOL( return_value, boolValueArg )
#define RETVAL_LONG( longValueArg )     ZVAL_LONG( return_value, longValueArg )
#define RETVAL_STRING( stringValueArg ) ZVAL_STRING( return_value, stringValueArg )

//////////////////////////////////////////////////////////////////////////////
//
// RETURN_* macros are used in "ConfigManager.c"
// via ELASTICAPM_MOCK_PHP_DEPS defined in unit tests' CMakeLists.txt
//
#ifdef ELASTICAPM_UNDER_IDE
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

#ifdef ELASTICAPM_UNDER_IDE
#   pragma clang diagnostic pop
#endif
//
// RETURN_* macros are used in "ConfigManager.c"
// via ELASTICAPM_MOCK_PHP_DEPS defined in unit tests' CMakeLists.txt
//
//////////////////////////////////////////////////////////////////////////////
