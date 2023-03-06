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

static inline
bool isStringViewPrefixIgnoringCase( StringView str, StringView prefix )
{
    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( str );
    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( prefix );

    if ( prefix.length > str.length )
    {
        return false;
    }

    ELASTIC_APM_FOR_EACH_INDEX( i, prefix.length )
    {
        if ( ! areCharsEqualIgnoringCase( str.begin[ i ], prefix.begin[ i ] ) )
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

#define ELASTIC_APM_DEFINE_ARRAY_VIEW_EX( ElementType, ViewTypeName ) \
    struct ViewTypeName \
    { \
        ElementType* values; \
        size_t size; \
    }; \
    typedef struct ViewTypeName ViewTypeName

#define ELASTIC_APM_DEFINE_ARRAY_VIEW( ElementType ) ELASTIC_APM_DEFINE_ARRAY_VIEW_EX( ElementType, ELASTIC_APM_PP_CONCAT( ElementType, ArrayView ) )

#define ELASTIC_APM_STATIC_ARRAY_TO_VIEW( ViewTypeName, staticArray ) ((ViewTypeName){ .values = &((staticArray)[0]), .size = ELASTIC_APM_STATIC_ARRAY_SIZE( staticArray ) })

#define ELASTIC_APM_EMPTY_ARRAY_VIEW( ViewTypeName ) ((ViewTypeName){ .values = NULL, .size = 0 })

ELASTIC_APM_DEFINE_ARRAY_VIEW_EX( int, IntArrayView );
ELASTIC_APM_DEFINE_ARRAY_VIEW( StringView );
ELASTIC_APM_DEFINE_ARRAY_VIEW( Int64 );
