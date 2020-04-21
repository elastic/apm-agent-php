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

void elasticApmCmockaAssertStringEqual(
        String a,                   /* <- argument #1 */
        String b,
        String aStringized,
        String bStringized,
        String filePath,
        int lineNumber,
        String printfFmt,           /* <- printf format is argument #7 */
        /* printfFmt args */ ... )  /* <- arguments for printf format placeholders start from argument #8 */
ELASTICAPM_PRINTF_ATTRIBUTE( /* fmtPos: */ 7, /* fmtArgsPos: */ 8 );

#define ELASTICAPM_CMOCKA_ASSERT_STRING_EQUAL( a, b, /* printfFmt, printfFmtArgs... */ ... ) \
    elasticApmCmockaAssertStringEqual( (a), (b), #a, #b, __FILE__,  __LINE__, ELASTICAPM_PRINTF_FORMAT_ARGS( __VA_ARGS__ ) )



void elasticApmCmockaAssertStringViewEqual( StringView a, StringView b, String filePath, int lineNumber );

#define ELASTICAPM_CMOCKA_ASSERT_STRING_VIEW_EQUAL( a, b ) \
    elasticApmCmockaAssertStringViewEqual( (a), (b), __FILE__,  __LINE__ )

#define ELASTICAPM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL( strView, literalString ) \
    elasticApmCmockaAssertStringViewEqual( (strView), ELASTICAPM_STRING_LITERAL_TO_VIEW( literalString ), __FILE__,  __LINE__)

#define ELASTICAPM_CMOCKA_ASSERT_FAILED( fileName, lineNumber, fmt, ... ) \
    do { \
        cm_print_error( fmt "\n", ##__VA_ARGS__ ); \
        fprintf( stderr, "[   LINE   ] --- %s:%u: " fmt "\n", extractLastPartOfFilePathString( fileName ), (UInt)(lineNumber), ##__VA_ARGS__ ); \
        _fail( extractLastPartOfFilePathString( fileName ), lineNumber ); \
    } while ( 0 )

void elasticApmCmockaAssertStringContainsIgnoreCase( String haystack, String needle, String fileName, int lineNumber );

#define ELASTICAPM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( haystack, needle ) \
    elasticApmCmockaAssertStringContainsIgnoreCase( (haystack), (needle), __FILE__,  __LINE__ )

#define ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( actual, expected ) \
    do { \
        if ( (actual) != (expected) ) \
            ELASTICAPM_CMOCKA_ASSERT_FAILED( __FILE__,  __LINE__, \
                "%s != %s: " \
                "%"PRId64" != %"PRId64, \
                (#actual), (#expected), \
                (Int64)(actual), (Int64)(expected) ); \
    } while ( 0 )

#define ELASTICAPM_CMOCKA_ASSERT_CHAR_EQUAL( actual, expected ) \
    do { \
        char bufferForActual[ escapeNonPrintableCharBufferSize ]; \
        char bufferForExpected[ escapeNonPrintableCharBufferSize ]; \
        if ( ((Byte)(actual)) != ((Byte)(expected)) ) \
            ELASTICAPM_CMOCKA_ASSERT_FAILED( __FILE__,  __LINE__, \
                "%s != %s: " \
                "'%s' (as int: %u) != '%s' (as int: %u)", \
                (#actual), (#expected), \
                escapeNonPrintableChar( (actual), bufferForActual ), \
                (UInt)((Byte)(actual)), \
                escapeNonPrintableChar( (expected), bufferForExpected ), \
                (UInt)((Byte)(expected)) ); \
    } while ( 0 )

#define ELASTICAPM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( expr ) \
    do { \
        ResultCode callAssertResultCode = (expr); \
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( callAssertResultCode, resultSuccess ); \
    } while ( 0 )


#define ELASTICAPM_CMOCKA_ASSERT( cond ) assert_true( cond )
#define ELASTICAPM_CMOCKA_ASSERT_VALID_PTR( ptr ) assert_ptr_not_equal( (ptr), NULL )
#define ELASTICAPM_CMOCKA_ASSERT_NULL_PTR( ptr ) assert_ptr_equal( (ptr), NULL )

#define ELASTICAPM_CMOCKA_FAIL() ELASTICAPM_CMOCKA_ASSERT_FAILED( __FILE__, __LINE__, "" )

#define ELASTICAPM_CMOCKA_UNIT_TEST_EX( func, setupFunc, teardownFunc ) \
    ( struct CMUnitTest ) \
    { \
        .test_func = &(func), \
        .name = extractLastPartOfFilePathString( __FILE__  ": " ELASTICAPM_PP_STRINGIZE( func ) ), \
        .initial_state = NULL, \
        .setup_func = setupFunc, \
        .teardown_func = teardownFunc \
    }

#define ELASTICAPM_CMOCKA_UNIT_TEST( func ) \
    ELASTICAPM_CMOCKA_UNIT_TEST_EX( func, perTestDefaultSetup, perTestDefaultTeardown )
