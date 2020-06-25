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

#include <stdlib.h>
#include <stdio.h>
#include <inttypes.h>
#include "basic_types.h"
#include "basic_macros.h"
#include "util.h"
#include "cmocka_wrapped_for_unit_tests.h"
#include "default_test_fixture.h"

//////////////////////////////////////////////////////////////////////////////
//
// ELASTIC_APM_PRINTF_FORMAT_ARGS
//
#define ELASTIC_APM_PRINTF_FORMAT_ARGS_0( ... ) "%s", ""
#define ELASTIC_APM_PRINTF_FORMAT_ARGS_1( ... ) __VA_ARGS__
#define ELASTIC_APM_PRINTF_FORMAT_ARGS_2( ... ) __VA_ARGS__
#define ELASTIC_APM_PRINTF_FORMAT_ARGS_3( ... ) __VA_ARGS__
#define ELASTIC_APM_PRINTF_FORMAT_ARGS_4( ... ) __VA_ARGS__
#define ELASTIC_APM_PRINTF_FORMAT_ARGS_5( ... ) __VA_ARGS__
#define ELASTIC_APM_PRINTF_FORMAT_ARGS_6( ... ) __VA_ARGS__
#define ELASTIC_APM_PRINTF_FORMAT_ARGS_7( ... ) __VA_ARGS__
#define ELASTIC_APM_PRINTF_FORMAT_ARGS_8( ... ) __VA_ARGS__
#define ELASTIC_APM_PRINTF_FORMAT_ARGS_9( ... ) __VA_ARGS__
#define ELASTIC_APM_PRINTF_FORMAT_ARGS_10( ... ) __VA_ARGS__

#define ELASTIC_APM_PRINTF_FORMAT_ARGS( ... ) \
    ELASTIC_APM_PP_EXPAND( ELASTIC_APM_PP_CONCAT( ELASTIC_APM_PRINTF_FORMAT_ARGS_, ELASTIC_APM_PP_VARIADIC_ARGS_COUNT( __VA_ARGS__ ) ) ) ( __VA_ARGS__ )

//
// ELASTIC_APM_PRINTF_FORMAT_ARGS
//
//////////////////////////////////////////////////////////////////////////////

void elasticApmCmockaAssertStringEqual(
        String a,                   /* <- argument #1 */
        String b,
        String aStringized,
        String bStringized,
        String filePath,
        int lineNumber,
        String printfFmt,           /* <- printf format is argument #7 */
        /* printfFmt args */ ... )  /* <- arguments for printf format placeholders start from argument #8 */
ELASTIC_APM_PRINTF_ATTRIBUTE( /* fmtPos: */ 7, /* fmtArgsPos: */ 8 );

#define ELASTIC_APM_CMOCKA_ASSERT_STRING_EQUAL( a, b, /* printfFmt, printfFmtArgs... */ ... ) \
    elasticApmCmockaAssertStringEqual( (a), (b), #a, #b, __FILE__,  __LINE__, ELASTIC_APM_PRINTF_FORMAT_ARGS( __VA_ARGS__ ) )


void elasticApmCmockaAssertStringViewEqual( StringView a, StringView b, String filePath, int lineNumber );

#define ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL( a, b ) \
    elasticApmCmockaAssertStringViewEqual( (a), (b), __FILE__,  __LINE__ )

#define ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL( strView, literalString ) \
    elasticApmCmockaAssertStringViewEqual( (strView), ELASTIC_APM_STRING_LITERAL_TO_VIEW( literalString ), __FILE__,  __LINE__)

#define ELASTIC_APM_CMOCKA_ASSERT_FAILED( fileName, lineNumber, fmt, ... ) \
    do { \
        cm_print_error( fmt "\n", ##__VA_ARGS__ ); \
        fprintf( stderr, "[   LINE   ] --- %s:%u: " fmt "\n", extractLastPartOfFilePathString( fileName ), (UInt)(lineNumber), ##__VA_ARGS__ ); \
        _fail( extractLastPartOfFilePathString( fileName ), lineNumber ); \
    } while ( 0 )

void elasticApmCmockaAssertStringContainsIgnoreCase( String haystack, String needle, String fileName, int lineNumber );

#define ELASTIC_APM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( haystack, needle ) \
    elasticApmCmockaAssertStringContainsIgnoreCase( (haystack), (needle), __FILE__,  __LINE__ )

#define ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( actual, expected ) \
    do { \
        if ( (actual) != (expected) ) \
            ELASTIC_APM_CMOCKA_ASSERT_FAILED( __FILE__,  __LINE__, \
                "%s != %s: " \
                "%"PRId64" != %"PRId64, \
                (#actual), (#expected), \
                (Int64)(actual), (Int64)(expected) ); \
    } while ( 0 )

#define ELASTIC_APM_CMOCKA_ASSERT_CHAR_EQUAL( actual, expected ) \
    do { \
        char bufferForActual[ escapeNonPrintableCharBufferSize ]; \
        char bufferForExpected[ escapeNonPrintableCharBufferSize ]; \
        if ( ((Byte)(actual)) != ((Byte)(expected)) ) \
            ELASTIC_APM_CMOCKA_ASSERT_FAILED( __FILE__,  __LINE__, \
                "%s != %s: " \
                "'%s' (as int: %u) != '%s' (as int: %u)", \
                (#actual), (#expected), \
                escapeNonPrintableChar( (actual), bufferForActual ), \
                (UInt)((Byte)(actual)), \
                escapeNonPrintableChar( (expected), bufferForExpected ), \
                (UInt)((Byte)(expected)) ); \
    } while ( 0 )

#define ELASTIC_APM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( expr ) \
    do { \
        ResultCode callAssertResultCode = (expr); \
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( callAssertResultCode, resultSuccess ); \
    } while ( 0 )


#define ELASTIC_APM_CMOCKA_ASSERT( cond ) assert_true( cond )
#define ELASTIC_APM_CMOCKA_ASSERT_VALID_PTR( ptr ) assert_ptr_not_equal( (ptr), NULL )
#define ELASTIC_APM_CMOCKA_ASSERT_NULL_PTR( ptr ) assert_ptr_equal( (ptr), NULL )

#define ELASTIC_APM_CMOCKA_FAIL() ELASTIC_APM_CMOCKA_ASSERT_FAILED( __FILE__, __LINE__, "" )

#define ELASTIC_APM_CMOCKA_UNIT_TEST_EX( func, setupFunc, teardownFunc ) \
    ( struct CMUnitTest ) \
    { \
        .test_func = &(func), \
        .name = extractLastPartOfFilePathString( __FILE__  ": " ELASTIC_APM_PP_STRINGIZE( func ) ), \
        .initial_state = NULL, \
        .setup_func = setupFunc, \
        .teardown_func = teardownFunc \
    }

#define ELASTIC_APM_CMOCKA_UNIT_TEST( func ) \
    ELASTIC_APM_CMOCKA_UNIT_TEST_EX( func, perTestDefaultSetup, perTestDefaultTeardown )
