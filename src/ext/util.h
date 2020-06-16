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
#include <stddef.h>
#include <string.h>
#include <stdio.h>
#include "constants.h"
#include "basic_types.h"
#include "StringView.h"
#include "basic_macros.h"
#include "basic_util.h"
#include "ResultCode.h"
#include "elasticapm_assert.h"
#include "elasticapm_clock.h"

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
    ELASTICAPM_ASSERT_VALID_PTR( str );

    for ( size_t i = 0 ; str[ i ] != '\0' ; ++i )
        if ( str[ i ] == originalChar ) str[ i ] = replacementChar;
}

static inline
void replaceCharInStringView( StringView strView, char originalChar, char replacementChar )
{
    ELASTICAPM_ASSERT_VALID_STRING_VIEW( strView );

    for ( size_t i = 0 ; i != strView.length ; ++i )
        if ( strView.begin[ i ] == originalChar ) ((char*)(strView.begin))[ i ] = replacementChar;
}

static inline
StringView stringToStringView( String str )
{
    ELASTICAPM_ASSERT_VALID_STRING( str );

    return (StringView) { .begin = str, .length = strlen( str ) };
}

static inline
StringView stringViewSkipFirstNChars( StringView strView, size_t numberOfCharsToSkip )
{
    ELASTICAPM_ASSERT_VALID_STRING_VIEW( strView );

    return (StringView) { .begin = strView.begin + numberOfCharsToSkip, .length = strView.length - numberOfCharsToSkip };
}

static inline char charToUpperCase( char c )
{
    if ( ELASTICAPM_IS_IN_INCLUSIVE_RANGE( 'a', c, 'z' ) )
    {
        return (char) ( (char) ( c - 'a' ) + 'A' );
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
    ELASTICAPM_ASSERT_VALID_STRING_VIEW( str );
    ELASTICAPM_ASSERT_VALID_STRING_VIEW( prefix );

    if ( prefix.length > str.length ) return false;

    ELASTICAPM_FOR_EACH_INDEX( i, prefix.length )
        if ( ! areCharsEqualIgnoringCase( str.begin[ i ], prefix.begin[ i ] ) )
            return false;

    return true;
}

static inline
bool areStringsEqualIgnoringCase( String str1, String str2 )
{
    ELASTICAPM_ASSERT_VALID_STRING( str1 );
    ELASTICAPM_ASSERT_VALID_STRING( str2 );

    for ( const char *p1 = str1, *p2 = str2; areCharsEqualIgnoringCase( *p1, *p2 ) ; ++p1, ++p2 )
        if ( *p1 == '\0' ) return true;

    return false;
}

static inline
bool areStringViewsEqual( StringView strVw1, StringView strVw2 )
{
    ELASTICAPM_ASSERT_VALID_STRING_VIEW( strVw1 );
    ELASTICAPM_ASSERT_VALID_STRING_VIEW( strVw2 );

    if ( strVw1.length != strVw2.length ) return false;

    ELASTICAPM_FOR_EACH_INDEX( i, strVw1.length )
        if ( strVw1.begin[ i ] != strVw2.begin[ i ] )
            return false;

    return true;
}

void genRandomIdAsHexString( UInt8 idSizeBytes, char* idAsHexStringBuffer, size_t idAsHexStringBufferSize );

#define ELASTICAPM_GEN_RANDOM_ID_AS_HEX_STRING( idSizeBytes, idAsHexStringBuffer ) \
    genRandomIdAsHexString( (idSizeBytes), (idAsHexStringBuffer), ELASTICAPM_STATIC_ARRAY_SIZE( (idAsHexStringBuffer) ) )

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
            if ( ELASTICAPM_IS_IN_INCLUSIVE_RANGE( '\x20', c, '\x7E' ) )
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
    ELASTICAPM_FOR_EACH_BACKWARDS( i, filePath.length )
        if ( isLocalOsFilePathSeparator( filePath.begin[ i ] ) ) return (int)i;

    return -1;
}

static inline
StringView extractLastPartOfFilePathStringView( StringView filePath )
{
    // some/file/path/Logger.c
    // some\file\path\Logger.c
    // ^^^^^^^^^^^^^^^

    ELASTICAPM_ASSERT_VALID_STRING_VIEW( filePath );

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
