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
#include <stddef.h>
#include <string.h>
#include <stdio.h>
#include <ctype.h>
#include "constants.h"
#include "basic_types.h"
#include "StringView.h"
#include "basic_macros.h"
#include "basic_util.h"
#include "ResultCode.h"
#include "elastic_apm_assert.h"
#include "elastic_apm_clock.h"

static inline
bool isEmtpyString( String str )
{
    return str[ 0 ] == '\0';
}

static inline
bool isNullOrEmtpyString( String str )
{
    return str == NULL || isEmtpyString( str );
}

static inline
void replaceCharInString( MutableString str, char originalChar, char replacementChar )
{
    ELASTIC_APM_ASSERT_VALID_PTR( str );

    for ( size_t i = 0 ; str[ i ] != '\0' ; ++i )
        if ( str[ i ] == originalChar ) str[ i ] = replacementChar;
}

static inline
void replaceCharInStringView( StringView strView, char originalChar, char replacementChar )
{
    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( strView );

    for ( size_t i = 0 ; i != strView.length ; ++i )
        if ( strView.begin[ i ] == originalChar ) ((char*)(strView.begin))[ i ] = replacementChar;
}

static inline
StringView stringToView( String str )
{
    ELASTIC_APM_ASSERT_VALID_STRING( str );

    return (StringView) { .begin = str, .length = strlen( str ) };
}

static inline
StringView stringViewSkipFirstNChars( StringView strView, size_t numberOfCharsToSkip )
{
    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( strView );

    return (StringView) { .begin = strView.begin + numberOfCharsToSkip, .length = strView.length - numberOfCharsToSkip };
}

static inline char charToUpperCase( char c )
{
    if ( ELASTIC_APM_IS_IN_INCLUSIVE_RANGE( 'a', c, 'z' ) )
    {
        return (char) ( (char) ( c - 'a' ) + 'A' );
    }

    return c;
}

static inline char charToLowerCase( char c )
{
    if ( ELASTIC_APM_IS_IN_INCLUSIVE_RANGE( 'A', c, 'Z' ) )
    {
        return (char) ( (char) ( c - 'A' ) + 'a' );
    }

    return c;
}

static inline void copyStringAsUpperCase( String src, /* out */ MutableString dst )
{
    size_t i = 0;
    for ( i = 0 ; src[ i ] != '\0' ; ++i ) dst[ i ] = charToUpperCase( src[ i ] );
    dst[ i ] = '\0';
}

static inline bool areCharsEqualIgnoringCase( char c1, char c2 )
{
    return charToUpperCase( c1 ) == charToUpperCase( c2 );
}

static inline bool areCharsEqual( char c1, char c2, bool shouldIgnoreCase )
{
    return shouldIgnoreCase ? areCharsEqualIgnoringCase( c1, c2 ) : ( c1 == c2 );
}

static inline
bool isStringViewPrefix( StringView str, StringView prefix, bool shouldIgnoreCase )
{
    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( str );
    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( prefix );

    if ( prefix.length > str.length )
    {
        return false;
    }

    ELASTIC_APM_FOR_EACH_INDEX( i, prefix.length )
    {
        if ( ! areCharsEqual( str.begin[ i ], prefix.begin[ i ], shouldIgnoreCase ) )
        {
            return false;
        }
    }

    return true;
}

static inline
bool isStringViewPrefixIgnoringCase( StringView str, StringView prefix )
{
    return isStringViewPrefix( str, prefix, /* shouldIgnoreCase */ true);
}

static inline
bool isStringViewSuffix( StringView str, StringView suffix )
{
    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( str );
    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( suffix );

    if ( suffix.length > str.length )
    {
        return false;
    }

    size_t strBeginIndex = str.length - suffix.length;
    ELASTIC_APM_FOR_EACH_INDEX( i, suffix.length )
    {
        if ( str.begin[ strBeginIndex + i ] != suffix.begin[ i ] )
        {
            return false;
        }
    }

    return true;
}

static inline
bool isStringViewSuffix( StringView str, StringView suffix )
{
    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( str );
    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( suffix );

    if ( suffix.length > str.length )
    {
        return false;
    }

    size_t strBeginIndex = str.length - suffix.length;
    ELASTIC_APM_FOR_EACH_INDEX( i, suffix.length )
    {
        if ( str.begin[ strBeginIndex + i ] != suffix.begin[ i ] )
        {
            return false;
        }
    }

    return true;
}

static inline
bool areStringsEqualIgnoringCase( String str1, String str2 )
{
    ELASTIC_APM_ASSERT_VALID_STRING( str1 );
    ELASTIC_APM_ASSERT_VALID_STRING( str2 );

    for ( const char *p1 = str1, *p2 = str2; areCharsEqualIgnoringCase( *p1, *p2 ) ; ++p1, ++p2 )
    {
        if ( *p1 == '\0' )
        {
            return true;
        }
    }

    return false;
}

static inline
bool areStringViewsEqualIgnoringCase( StringView strVw1, StringView strVw2 )
{
    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( strVw1 );
    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( strVw2 );

    if ( strVw1.length != strVw2.length )
    {
        return false;
    }

    ELASTIC_APM_FOR_EACH_INDEX( i, strVw1.length )
    {
        if ( ! areCharsEqualIgnoringCase( strVw1.begin[ i ], strVw2.begin[ i ] ) )
        {
            return false;
        }
    }

    return true;
}

static inline
bool areStringViewsEqual( StringView strVw1, StringView strVw2 )
{
    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( strVw1 );
    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( strVw2 );

    if ( strVw1.length != strVw2.length )
    {
        return false;
    }

    ELASTIC_APM_FOR_EACH_INDEX( i, strVw1.length )
    {
        if ( strVw1.begin[ i ] != strVw2.begin[ i ] )
        {
            return false;
        }
    }

    return true;
}

static inline
bool areEqualNullableStrings( String str1, String str2 )
{
    if ( ( str1 == NULL ) && ( str2 == NULL ) ) return true;
    if ( ( str1 == NULL ) || ( str2 == NULL ) ) return false;
    return ( strcmp( str1, str2 ) == 0 );
}

enum { escapeNonPrintableCharBufferSize = 10 };

static inline
String escapeNonPrintableChar( char c, char buffer[ escapeNonPrintableCharBufferSize ] )
{
    switch ( c )
    {
        case '\0': return "\\0";

        case '\a': return "\\a";

        case '\b': return "\\b";

        case '\f': return "\\f";

        case '\n': return "\\n";

        case '\r': return "\\r";

        case '\t': return "\\t";

        case '\v': return "\\v";

        default:
            // According to https://en.wikipedia.org/wiki/ASCII#Printable_characters
            // Codes 20 (hex) to 7E (hex), known as the printable characters
            if ( ELASTIC_APM_IS_IN_INCLUSIVE_RANGE( '\x20', c, '\x7E' ) )
            {
                buffer[ 0 ] = c;
                buffer[ 1 ] = '\0';
            }
            else
                snprintf( buffer, escapeNonPrintableCharBufferSize, "\\x%X", (UInt)((unsigned char)c) );

            return buffer;
    }
}

static inline
bool isLocalOsFilePathSeparator( char c )
{
    return
            ( c == '/' )
            #ifdef PHP_WIN32
            ||
            ( c == '\\' )
        #endif
            ;
}

static inline
int findLastPathSeparatorPosition( StringView filePath )
{
    ELASTIC_APM_FOR_EACH_BACKWARDS( i, filePath.length )
        if ( isLocalOsFilePathSeparator( filePath.begin[ i ] ) ) return (int)i;

    return -1;
}

static inline
StringView extractLastPartOfFilePathStringView( StringView filePath )
{
    // some/file/path/Logger.c
    // some\file\path\Logger.c
    // ^^^^^^^^^^^^^^^

    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( filePath );

    int lastPathSeparatorPosition = findLastPathSeparatorPosition( filePath );
    if ( lastPathSeparatorPosition == -1 ) return filePath;
    return stringViewSkipFirstNChars( filePath, (size_t)( lastPathSeparatorPosition + 1 ) );
}

static inline
String extractLastPartOfFilePathString( String filePath )
{
    return extractLastPartOfFilePathStringView( makeStringViewFromString( filePath ) ).begin;
}

static inline
size_t calcAlignedSize( size_t size, size_t alignment )
{
    const size_t remainder = size % alignment;
    return ( remainder == 0 ) ? size : ( size - remainder + alignment );
}

static inline
bool isWhiteSpace( char c )
{
    return c == ' ' || c == '\t' || c == '\n' || c == '\r';
}

StringView trimStringView( StringView src );

StringView findEndOfLineSequence( StringView text );

static inline
bool isDecimalDigit( char c )
{
    return isdigit( c ) != 0;
}

typedef bool (* CharPredicate)( char c );

bool findCharByPredicate( StringView src, CharPredicate predicate, size_t* foundPosition );

static inline
bool isDecimalDigitOrSign( char c )
{
    return ( c == '-' ) || isDecimalDigit( c );
}

static inline
bool isNotDecimalDigitOrSign( char c )
{
    return ! isDecimalDigitOrSign( c );
}

ResultCode parseDecimalInteger( StringView inputString, /* out */ Int64* result );
ResultCode parseDecimalIntegerWithUnits( StringView inputString, StringView unitNames[], size_t numberOfUnits, /* out */ Int64* valueInUnits, /* out */ size_t* unitsIndex );

static inline
bool ifThen( bool ifCond, bool thenCond )
{
    return ( ! ifCond ) || thenCond;
}

enum SizeUnits
{
    sizeUnits_byte,
    sizeUnits_kibibyte,
    sizeUnits_mebibyte,
    sizeUnits_gibibyte,

    numberOfSizeUnits
};
typedef enum SizeUnits SizeUnits;
extern StringView sizeUnitsNames[ numberOfSizeUnits ];

struct Size
{
    Int64 valueInUnits;
    SizeUnits units;
};
typedef struct Size Size;

static inline
bool isValidSizeUnits( SizeUnits units )
{
    return ( sizeUnits_byte <= units ) && ( units < numberOfSizeUnits );
}

#define ELASTIC_APM_UNKNOWN_SIZE_UNITS_AS_STRING "<UNKNOWN SizeUnits>"

static inline
String sizeUnitsToString( SizeUnits sizeUnits )
{
    if ( isValidSizeUnits( sizeUnits ) )
    {
        return sizeUnitsNames[ sizeUnits ].begin;
    }
    return ELASTIC_APM_UNKNOWN_SIZE_UNITS_AS_STRING;
}

static inline
Size makeSize( Int64 valueInUnits, SizeUnits units )
{
    return (Size){ .valueInUnits = valueInUnits, .units = units };
}

ResultCode parseSize( StringView inputString, SizeUnits defaultUnits, /* out */ Size* result );

String streamSize( Size size, TextOutputStream* txtOutStream );

Int64 sizeToBytes( Size size );

static inline
String stringIfNotNullElse( String str, String elseStr )
{
    return str == NULL ? elseStr: str;
}

static inline
ResultCode safeStringCopy( StringView src, char* dstBuf, size_t dstBufCapacity )
{
    ResultCode resultCode;

    if ( src.length == 0 )
    {
        ELASTIC_APM_SET_RESULT_CODE_TO_SUCCESS_AND_GOTO_FINALLY();
    }

    // +1 for terminating '\0'
    if ( src.length + 1 > dstBufCapacity )
    {
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE_EX( resultBufferIsTooSmall );
    }

#ifdef PHP_WIN32
    // errno_t strncpy_s( char *restrict dest, size_t destsz, const char *restrict src, size_t count );
    // returns zero on success, returns non-zero on error. Also, on error, writes zero to dest[0]
    // (unless dest is a null pointer or destsz is zero or greater than RSIZE_MAX)
    // and may clobber the rest of the destination array with unspecified values.

    errno_t strncpy_s_ret_val = strncpy_s( /* dest */ dstBufCapacity, /* destsz */ dstBufCapacity, /* src */ src.begin, /* count */ src.length );
    if ( strncpy_s_ret_val != 0 )
    {
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }
#else // #ifdef PHP_WIN32
    // char *strncpy( char *dest, const char *src, size_t count );
    // Copies at most count characters of the character array pointed to by src (including the terminating null character,
    // but not any of the characters that follow the null character) to character array pointed to by dest.
    // If count is reached before the entire array src was copied, the resulting character array is not null-terminated.

    strncpy( /* dest */ dstBuf, /* src */ src.begin, /* count */ src.length );
    dstBuf[ src.length ] = '\0';
#endif // #ifdef PHP_WIN32

    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    goto finally;
}

static inline
ResultCode appendToString( StringView suffixToAppend, size_t bufCapacity, /* in */ char* bufBegin, /* in,out */ size_t* bufContentLen )
{
    ResultCode resultCode;

    if ( suffixToAppend.length == 0 )
    {
        ELASTIC_APM_SET_RESULT_CODE_TO_SUCCESS_AND_GOTO_FINALLY();
    }

    ELASTIC_APM_ASSERT_VALID_PTR( bufBegin );
    ELASTIC_APM_ASSERT_VALID_PTR( bufContentLen );
    ELASTIC_APM_ASSERT( *bufContentLen < bufCapacity, "*bufContentLen: %"PRIu64", bufCapacity: %"PRIu64, (UInt64)(*bufContentLen), (UInt64)bufCapacity );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( safeStringCopy( /* src */ suffixToAppend, /* dstBuf */ bufBegin + *bufContentLen, bufCapacity - *bufContentLen ) );

    *bufContentLen += suffixToAppend.length;
    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    goto finally;
}

struct StringBuffer
{
    char* begin;
    size_t size;
};
typedef struct StringBuffer StringBuffer;

#define ELASTIC_APM_MAKE_STRING_BUFFER( beginArg, sizeArg ) ((StringBuffer){ .begin = (beginArg), .size = (sizeArg) })

#define ELASTIC_APM_EMPTY_STRING_BUFFER ( ELASTIC_APM_MAKE_STRING_BUFFER(  NULL, 0 ) )

static inline
StringView stringBufferToView( StringBuffer strBuf )
{
    // -1 since terminating '\0' is counted in buffer's size but not in string's length
    return (StringView)
    {
        .begin = strBuf.begin,
        .length = (strBuf.begin == NULL) ? 0 : (strBuf.size - 1)
    };
}

static inline
ResultCode appendToStringBuffer( StringView suffixToAppend, StringBuffer buf, /* in,out */ size_t* bufContentLen )
{
    return appendToString( suffixToAppend, buf.size, buf.begin, /* in,out */ bufContentLen );
}
