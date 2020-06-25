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
#include <inttypes.h> // PRId64, PRIu64
#include <stdarg.h>
#include "basic_macros.h" // ELASTIC_APM_NO_RETURN_ATTRIBUTE, ELASTIC_APM_PRINTF_ATTRIBUTE
#include "basic_types.h" // Int64, UInt64
#include "internal_checks.h"

void elasticApmAbort() ELASTIC_APM_NO_RETURN_ATTRIBUTE;

#define ELASTIC_APM_STATIC_ASSERT( cond ) \
    typedef char ELASTIC_APM_PP_CONCAT( elastic_apm_static_assert_t_, ELASTIC_APM_PP_CONCAT( __LINE__, ELASTIC_APM_PP_CONCAT( _, __COUNTER__ ) ) ) [ (cond) ? 1 : -1 ]

#ifndef ELASTIC_APM_ASSERT_ENABLED_01
#   if defined( ELASTIC_APM_ASSERT_ENABLED ) && ( ELASTIC_APM_ASSERT_ENABLED == 0 )
#       define ELASTIC_APM_ASSERT_ENABLED_01 0
#   else
#       define ELASTIC_APM_ASSERT_ENABLED_01 1
#   endif
#endif

#if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )

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

#ifndef ELASTIC_APM_ASSERT_DEFAULT_LEVEL
#   if ( ELASTIC_APM_IS_DEBUG_BUILD_01 != 0 )
#       define ELASTIC_APM_ASSERT_DEFAULT_LEVEL assertLevel_all
#   else
#       define ELASTIC_APM_ASSERT_DEFAULT_LEVEL assertLevel_off
#   endif
#endif

void elasticApmAssertFailed(
        const char* filePath /* <- argument #1 */
        , unsigned int lineNumber
        , const char* funcName
        , const char* msgPrintfFmt /* <- printf format is argument #4 */
        , /* msgPrintfFmtArgs */ ... /* <- arguments for printf format placeholders start from argument #5 */
) ELASTIC_APM_PRINTF_ATTRIBUTE( /* printfFmtPos: */ 4, /* printfFmtArgsPos: */ 5 ) ELASTIC_APM_NO_RETURN_ATTRIBUTE;

void vElasticApmAssertFailed(
        const char* filePath
        , unsigned int lineNumber
        , const char* funcName
        , const char* msgPrintfFmt
        , va_list msgPrintfFmtArgs
) ELASTIC_APM_NO_RETURN_ATTRIBUTE;

#ifndef ELASTIC_APM_ASSERT_FAILED_FUNC
#   define ELASTIC_APM_ASSERT_FAILED_FUNC elasticApmAssertFailed
#   define ELASTIC_APM_ASSERT_FAILED_FUNC_NO_RETURN_ATTRIBUTE ELASTIC_APM_NO_RETURN_ATTRIBUTE
#endif

#ifndef ELASTIC_APM_ASSERT_FAILED_FUNC_NO_RETURN_ATTRIBUTE
#   define ELASTIC_APM_ASSERT_FAILED_FUNC_NO_RETURN_ATTRIBUTE
#endif

// Declare to avoid warnings
void ELASTIC_APM_ASSERT_FAILED_FUNC(
        const char* filePath /* <- argument #1 */
        , unsigned int lineNumber
        , const char* funcName
        , const char* msgPrintfFmt /* <- printf format is argument #4 */
        , /* msgPrintfFmtArgs */ ... /* <- arguments for printf format placeholders start from argument #5 */
) ELASTIC_APM_PRINTF_ATTRIBUTE( /* printfFmtPos: */ 4, /* printfFmtArgsPos: */ 5 ) ELASTIC_APM_ASSERT_FAILED_FUNC_NO_RETURN_ATTRIBUTE;

AssertLevel getGlobalAssertLevel();

#define ELASTIC_APM_ASSERT_WITH_LEVEL( level, cond, msgPrintfFmt, /* msgPrintfFmtArgs */ ... ) \
    do { \
        if ( getGlobalAssertLevel() >= (level) && ( ! (cond) ) ) \
            ELASTIC_APM_ASSERT_FAILED_FUNC \
            ( \
                __FILE__ \
                , __LINE__ \
                , __FUNCTION__ \
                , "Assertion failed! Condition: %s" "%s" msgPrintfFmt \
                , #cond \
                , ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE( "", ". " ,##__VA_ARGS__ ) \
                ,##__VA_ARGS__ \
            ); \
    } while ( 0 )

#define ELASTIC_APM_ASSERT_VALID_OBJ_WITH_LEVEL( level, assertValidObjCall ) \
    do { \
        if ( getGlobalAssertLevel() >= level ) \
            (void)(assertValidObjCall); \
    } while ( 0 )

#else // #if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )

#define ELASTIC_APM_ASSERT_WITH_LEVEL( level, cond, msgPrintfFmt, /* msgPrintfFmtArgs */ ... ) ELASTIC_APM_NOOP_STATEMENT
#define ELASTIC_APM_ASSERT_VALID_OBJ_WITH_LEVEL( level, assertValidObjCall ) ELASTIC_APM_NOOP_STATEMENT

#endif // #if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )

#define ELASTIC_APM_ASSERT( cond, msgPrintfFmt, /* msgPrintfFmtArgs: */ ... ) \
    ELASTIC_APM_ASSERT_WITH_LEVEL( assertLevel_O_1, cond, msgPrintfFmt /* msgPrintfFmtArgs: */ , ##__VA_ARGS__ ) \
/**/

#define ELASTIC_APM_ASSERT_O_N( cond, /* , msg: */ ... ) \
    ELASTIC_APM_ASSERT_WITH_LEVEL( assertLevel_O_n, cond, msgPrintfFmt /* msgPrintfFmtArgs: */ , ##__VA_ARGS__ ) \
/**/

#define ELASTIC_APM_ASSERT_VALID_OBJ( assertValidObjCall ) ELASTIC_APM_ASSERT_VALID_OBJ_WITH_LEVEL( assertLevel_O_1, assertValidObjCall )
#define ELASTIC_APM_ASSERT_VALID_OBJ_O_N( assertValidObjCall ) ELASTIC_APM_ASSERT_VALID_OBJ_WITH_LEVEL( assertLevel_O_n, assertValidObjCall )

static inline
bool isValidPtr( const void* ptr )
{
    return ptr != NULL;
}

#define ELASTIC_APM_ASSERT_VALID_PTR( ptr ) ELASTIC_APM_ASSERT_VALID_OBJ( isValidPtr( ptr ) )

#define ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( ptrToPtr ) ELASTIC_APM_ASSERT( isValidPtr( ptrToPtr ) && ( *(ptrToPtr) == NULL ), "" )
#define ELASTIC_APM_ASSERT_VALID_IN_PTR_TO_PTR( ptrToPtr ) ELASTIC_APM_ASSERT( isValidPtr( ptrToPtr ) && isValidPtr( *(ptrToPtr) ), "" )
#define ELASTIC_APM_ASSERT_VALID_STRING( str ) ELASTIC_APM_ASSERT_VALID_PTR( str )

#define ELASTIC_APM_ASSERT_PTR_IS_NULL( ptr ) ELASTIC_APM_ASSERT( (ptr) == NULL, #ptr ": %p", (ptr) )

#define ELASTIC_APM_ASSERT_OP( lhsOperand, binaryRelOperator, rhsOperand, operandType, printfFmtForOperandType ) \
    ELASTIC_APM_ASSERT \
    ( \
        ((operandType)(lhsOperand)) binaryRelOperator ((operandType)(rhsOperand)) \
        , #lhsOperand ": %"printfFmtForOperandType ", " #rhsOperand ": %"printfFmtForOperandType \
        , ((operandType)(lhsOperand)), ((operandType)(rhsOperand)) \
    ) \
/**/

#define ELASTIC_APM_ASSERT_EQ_UINT64( lhsOperand, rhsOperand )  ELASTIC_APM_ASSERT_OP( lhsOperand, ==, rhsOperand, UInt64, PRIu64 )
#define ELASTIC_APM_ASSERT_GE_UINT64( lhsOperand, rhsOperand )  ELASTIC_APM_ASSERT_OP( lhsOperand, >=, rhsOperand, UInt64, PRIu64 )
#define ELASTIC_APM_ASSERT_GT_UINT64( lhsOperand, rhsOperand )  ELASTIC_APM_ASSERT_OP( lhsOperand, >, rhsOperand, UInt64, PRIu64 )
#define ELASTIC_APM_ASSERT_LE_UINT64( lhsOperand, rhsOperand )  ELASTIC_APM_ASSERT_OP( lhsOperand, <=, rhsOperand, UInt64, PRIu64 )
#define ELASTIC_APM_ASSERT_LT_UINT64( lhsOperand, rhsOperand )  ELASTIC_APM_ASSERT_OP( lhsOperand, <, rhsOperand, UInt64, PRIu64 )

#define ELASTIC_APM_ASSERT_OP_PTR( lhsOperand, binaryRelOperator, rhsOperand ) \
    ELASTIC_APM_ASSERT \
    ( \
        (lhsOperand) binaryRelOperator (rhsOperand) \
        , #lhsOperand ": %p, " #rhsOperand ": %p" \
        , (lhsOperand), (rhsOperand) \
    ) \
/**/

#define ELASTIC_APM_ASSERT_EQ_PTR( lhsOperand, rhsOperand )  ELASTIC_APM_ASSERT_OP_PTR( lhsOperand, ==, rhsOperand )
#define ELASTIC_APM_ASSERT_GE_PTR( lhsOperand, rhsOperand )  ELASTIC_APM_ASSERT_OP_PTR( lhsOperand, >=, rhsOperand )
#define ELASTIC_APM_ASSERT_LE_PTR( lhsOperand, rhsOperand )  ELASTIC_APM_ASSERT_OP_PTR( lhsOperand, <=, rhsOperand )
#define ELASTIC_APM_ASSERT_LT_PTR( lhsOperand, rhsOperand )  ELASTIC_APM_ASSERT_OP_PTR( lhsOperand, <, rhsOperand )

#define ELASTIC_APM_ASSERT_EQ_CHAR( lhsOperand, rhsOperand ) \
    ELASTIC_APM_ASSERT \
    ( \
        (lhsOperand) == (rhsOperand) \
        , #lhsOperand ": %c (as unsigned int: %u), " #rhsOperand ": %c (as unsigned int: %u)" \
        , (lhsOperand), ((unsigned int)(lhsOperand)), (rhsOperand), ((unsigned int)(rhsOperand)) \
    ) \
/**/

#define ELASTIC_APM_ASSERT_IN_INCLUSIVE_RANGE_UINT64( rangeBegin, x, rangeEnd ) \
    ELASTIC_APM_ASSERT( ELASTIC_APM_IS_IN_INCLUSIVE_RANGE( rangeBegin, x, rangeEnd ) \
        , #rangeBegin": %"PRIu64", " #x ": %"PRIu64", " #rangeEnd ": %"PRIu64 \
        , (UInt64)(rangeBegin), (UInt64)(x), (UInt64)(rangeEnd) ) \
/**/

#define ELASTIC_APM_ASSERT_IN_END_EXCLUDED_RANGE_UINT64( rangeBeginIncluded, x, rangeEndExcluded ) \
    ELASTIC_APM_ASSERT( ELASTIC_APM_IS_IN_END_EXCLUDED_RANGE( rangeBeginIncluded, x, rangeEndExcluded ) \
        , #rangeBeginIncluded": %"PRIu64", " #x ": %"PRIu64", " #rangeEndExcluded ": %"PRIu64 \
        , (UInt64)(rangeBeginIncluded), (UInt64)(x), (UInt64)(rangeEndExcluded) ) \
/**/

struct TextOutputStream;
typedef struct TextOutputStream TextOutputStream;
String streamAssertLevel( AssertLevel level, TextOutputStream* txtOutStream );
