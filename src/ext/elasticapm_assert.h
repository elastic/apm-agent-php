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
#include <stdlib.h> // NULL
#include "basic_macros.h" // ELASTICAPM_NO_RETURN_ATTRIBUTE
#include "internal_checks.h"

void elasticApmAbort() ELASTICAPM_NO_RETURN_ATTRIBUTE;

#define ELASTICAPM_STATIC_ASSERT( cond ) \
    typedef char ELASTICAPM_PP_CONCAT( elasticapm_static_assert_t_, ELASTICAPM_PP_CONCAT( __LINE__, ELASTICAPM_PP_CONCAT( _, __COUNTER__ ) ) ) [ (cond) ? 1 : -1 ]

#ifndef ELASTICAPM_ASSERT_ENABLED_01
#   if defined( ELASTICAPM_ASSERT_ENABLED ) && ( ELASTICAPM_ASSERT_ENABLED == 0 )
#       define ELASTICAPM_ASSERT_ENABLED_01 0
#   else
#       define ELASTICAPM_ASSERT_ENABLED_01 1
#   endif
#endif

#if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )

enum AssertLevel
{
    assertLevel_not_set = -1,
    assertLevel_off = 0,

    assertLevel_O_1,
    assertLevel_O_n,

    assertLevel_all,
    numberOfAssertLevels = assertLevel_all + 1
};
typedef enum AssertLevel AssertLevel;

extern const char* assertLevelNames[ numberOfAssertLevels ];

AssertLevel internalChecksToAssertLevel( InternalChecksLevel internalChecksLevel );

#ifndef ELASTICAPM_ASSERT_DEFAULT_LEVEL
#   if ( ELASTICAPM_IS_DEBUG_BUILD_01 != 0 )
#       define ELASTICAPM_ASSERT_DEFAULT_LEVEL assertLevel_all
#   else
#       define ELASTICAPM_ASSERT_DEFAULT_LEVEL assertLevel_off
#   endif
#endif

void elasticApmAssertFailed(
        const char* condExpr,
        const char* filePath,
        unsigned int lineNumber,
        const char* funcName,
        const char* msg );

#ifndef ELASTICAPM_ASSERT_FAILED_FUNC
#   define ELASTICAPM_ASSERT_FAILED_FUNC elasticApmAssertFailed
#   define ELASTICAPM_ASSERT_FAILED_FUNC_NO_RETURN_ATTRIBUTE ELASTICAPM_NO_RETURN_ATTRIBUTE
#endif

#ifndef ELASTICAPM_ASSERT_FAILED_FUNC_NO_RETURN_ATTRIBUTE
#   define ELASTICAPM_ASSERT_FAILED_FUNC_NO_RETURN_ATTRIBUTE
#endif

// Declare to avoid warnings
void ELASTICAPM_ASSERT_FAILED_FUNC(
        const char* condExpr,
        const char* filePath,
        unsigned int lineNumber,
        const char* funcName,
        const char* msg )
        ELASTICAPM_ASSERT_FAILED_FUNC_NO_RETURN_ATTRIBUTE;

AssertLevel getGlobalAssertLevel();

#define ELASTICAPM_ASSERT_WITH_LEVEL( level, cond, /* msg: */ ... ) \
    do { \
        if ( getGlobalAssertLevel() >= (level) && ( ! (cond) ) ) \
            ELASTICAPM_ASSERT_FAILED_FUNC ( \
                    #cond, \
                    __FILE__, \
                    __LINE__, \
                    __FUNCTION__, \
                    /* msg: */ ( NULL, ##__VA_ARGS__ ) ); \
    } while ( 0 )

#define ELASTICAPM_ASSERT_VALID_OBJ_WITH_LEVEL( level, assertValidObjCall ) \
    do { \
        if ( getGlobalAssertLevel() >= level ) \
            (void)(assertValidObjCall); \
    } while ( 0 )

#else // #if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )

#define ELASTICAPM_ASSERT_WITH_LEVEL( level, cond, /* msg: */ ... ) ELASTICAPM_NOOP_STATEMENT
#define ELASTICAPM_ASSERT_VALID_OBJ_WITH_LEVEL( level, assertValidObjCall ) ELASTICAPM_NOOP_STATEMENT

#endif // #if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )

#define ELASTICAPM_ASSERT( cond, /* , msg: */ ... ) ELASTICAPM_ASSERT_WITH_LEVEL( assertLevel_O_1, cond /* msg: */ , ##__VA_ARGS__ )
#define ELASTICAPM_ASSERT_O_N( cond, /* , msg: */ ... ) ELASTICAPM_ASSERT_WITH_LEVEL( assertLevel_O_n, cond /* msg: */ , ##__VA_ARGS__ )

#define ELASTICAPM_ASSERT_VALID_OBJ( assertValidObjCall ) ELASTICAPM_ASSERT_VALID_OBJ_WITH_LEVEL( assertLevel_O_1, assertValidObjCall )
#define ELASTICAPM_ASSERT_VALID_OBJ_O_N( assertValidObjCall ) ELASTICAPM_ASSERT_VALID_OBJ_WITH_LEVEL( assertLevel_O_n, assertValidObjCall )

static inline
bool isValidPtr( const void* ptr )
{
    return ptr != NULL;
}

#define ELASTICAPM_ASSERT_VALID_PTR( ptr ) ELASTICAPM_ASSERT_VALID_OBJ( isValidPtr( ptr ) )

#define ELASTICAPM_ASSERT_VALID_OUT_PTR_TO_PTR( ptrToPtr ) ELASTICAPM_ASSERT( isValidPtr( ptrToPtr ) && ( *(ptrToPtr) == NULL ) )
#define ELASTICAPM_ASSERT_VALID_IN_PTR_TO_PTR( ptrToPtr ) ELASTICAPM_ASSERT( isValidPtr( ptrToPtr ) && isValidPtr( *(ptrToPtr) ) )
#define ELASTICAPM_ASSERT_VALID_STRING( str ) ELASTICAPM_ASSERT_VALID_PTR( str )

struct TextOutputStream;
typedef struct TextOutputStream TextOutputStream;
typedef const char* String;
String streamAssertLevel( AssertLevel level, TextOutputStream* txtOutStream );
