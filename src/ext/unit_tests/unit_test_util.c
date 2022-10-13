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

#include "unit_test_util.h"
#include <string.h>
#include "ResultCode.h"
#include "elastic_apm_assert.h"
#include "elastic_apm_alloc.h"
#include "log.h"

static
void elasticApmCmockaFail( String filePath, int lineNumber ) ELASTIC_APM_NO_RETURN_ATTRIBUTE;

static
void elasticApmCmockaFail( String filePath, int lineNumber )
{
    _fail( extractLastPartOfFilePathString( filePath ), lineNumber );
    elasticApmAbort();
}

#define ELASTIC_APM_CMOCKA_PRINT_ERROR( printfFmt, /* printfFmtArgs: */ ... ) \
        cm_print_error( printfFmt"\n", ##__VA_ARGS__ )

void elasticApmCmockaAssertStringEqual(
        String left,
        String right,
        String leftExprStringized,
        String rightExprStringized,
        String filePath,
        int lineNumber,
        String printfFmt,
        /* printfFmt args */ ... )
{
    if ( strcmp( left, right ) == 0) return;

    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE * 10 ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    va_list msgArgs;
    va_start( msgArgs, printfFmt );
    String additionalMessage = streamVPrintf( &txtOutStream, printfFmt, msgArgs );
    va_end( msgArgs );

    ELASTIC_APM_CMOCKA_PRINT_ERROR(
            "Assertion:" "\n\n"
            "\t" "%s == %s" "\n\n"
            "failed:" "\n\n"
            "\t" "%s:" "\n\n"
            "\t\t" "`%s'" "\n\n"
            "\t" "%s:" "\n\n"
            "\t\t" "`%s'" "\n\n"
            "%s\n",
            leftExprStringized, rightExprStringized,
            leftExprStringized, left,
            rightExprStringized, right,
            additionalMessage );

    elasticApmCmockaFail( filePath, lineNumber );
}

static
ResultCode strdup_StringView_as_C_string( StringView src, String* dst )
{
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( dst );

    ResultCode resultCode;
    char* dup_as_C_string = NULL;

    ELASTIC_APM_PEMALLOC_STRING_IF_FAILED_GOTO( src.length + 1, dup_as_C_string );
    strncpy( dup_as_C_string, src.begin, src.length );
    dup_as_C_string[ src.length ] = '\0';

    *dst = dup_as_C_string;
    resultCode = resultSuccess;

    finally:
    return resultCode;

    failure:
    ELASTIC_APM_PEMALLOC_STRING_IF_FAILED_GOTO( src.length + 1, dup_as_C_string );
    goto finally;
}

static
ResultCode assert_StringView_equal_impl( StringView a, StringView b, String file, int line )
{
    ResultCode resultCode;
    String a_as_C_string = NULL;
    String b_as_C_string = NULL;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( strdup_StringView_as_C_string( a, &a_as_C_string ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( strdup_StringView_as_C_string( b, &b_as_C_string ) );

    _assert_string_equal( a_as_C_string, b_as_C_string, file, line );

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_PEFREE_STRING_SIZE_AND_SET_TO_NULL( a.length + 1, a_as_C_string );
    ELASTIC_APM_PEFREE_STRING_SIZE_AND_SET_TO_NULL( b.length + 1, b_as_C_string );

    ELASTIC_APM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( resultCode );
    return resultCode;

    failure:
    goto finally;
}

void elasticApmCmockaAssertStringViewEqual( StringView a, StringView b, String filePath, int lineNumber )
{
    ELASTIC_APM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( assert_StringView_equal_impl( a, b, filePath, lineNumber ) );
}

static
const char* find_substring_ignore_case( String haystack, String needle )
{
    ELASTIC_APM_ASSERT_VALID_STRING( haystack );
    ELASTIC_APM_ASSERT_VALID_STRING( needle );

    // Edge case: The empty string is a substring of everything
    if ( isEmtpyString( needle ) ) return haystack;

    // Loop over all possible start positions
    for ( const char* pHaystack = haystack ; *pHaystack != '\0' ; ++pHaystack )
    {
        // See if needle matches starting from pHaystack
        for ( const char* pNeedle = needle, * pHaystack2 = pHaystack ; true ; ++pNeedle, ++pHaystack2 )
        {
            // If we reached end of needle then we found a match that starts from pHaystack
            if ( *pNeedle == '\0' ) return pHaystack;

            // If we found mismatch then we need to go back to outer loop
            if ( ! areCharsEqualIgnoringCase( *pNeedle, *pHaystack2 ) ) break;

            // If we reached end of haystack then we won't be able to find a match
            // because the remainder of haystack is too short to contain needle
            if ( *pHaystack2 == '\0' ) return NULL;
        }
    }

    return NULL;
}

void elasticApmCmockaAssertStringContainsIgnoreCase(
        String haystack,
        String needle,
        String fileName,
        int lineNumber )
{
    if ( find_substring_ignore_case( haystack, needle ) != NULL ) return;

    ELASTIC_APM_CMOCKA_ASSERT_FAILED(
        fileName,
        lineNumber,
        "Assertion that a string contains another one (ignoring case) has failed."
        " haystack: `%s'. needle: `%s'.",
        haystack, needle );
}
