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
#include <stdio.h>
#include <string.h>
#include <stdarg.h>
#include <inttypes.h>
#include "elasticapm_assert.h"
#include "basic_types.h"
#include "StringView.h"
#include "basic_macros.h"
#include "basic_util.h"

struct TextOutputStream
{
    char* bufferBegin;
    size_t bufferSize;
    char* freeSpaceBegin;
    bool isOverflowed;

    // Append terminating '\0' automatically in streamInt(), streamString(), etc.
    bool autoTermZero;

    // If shouldEncloseUserString is true then user provided strings are written as `text' (enclosed in quotes)
    // otherwise user provided strings are are written as text (i.e., no quotes)
    bool shouldEncloseUserString;
};
typedef struct TextOutputStream TextOutputStream;

struct TextOutputStreamState
{
    char* freeSpaceBegin;
    // Append terminating '\0' automatically in streamInt(), streamString(), etc.
    bool autoTermZero;
};
typedef struct TextOutputStreamState TextOutputStreamState;

#define ELASTICAPM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER "<NOT ENOUGH SPACE in TextOutputStream>"

#define ELASTICAPM_TEXT_OUTPUT_STREAM_OVERFLOWED_MARKER "..." ELASTICAPM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER
#define ELASTICAPM_TEXT_OUTPUT_STREAM_OVERFLOWED_MARKER_LENGTH ( sizeof( ELASTICAPM_TEXT_OUTPUT_STREAM_OVERFLOWED_MARKER ) - 1 )

#define ELASTICAPM_TEXT_OUTPUT_STREAM_RESERVED_SPACE_SIZE ( ELASTICAPM_TEXT_OUTPUT_STREAM_OVERFLOWED_MARKER_LENGTH + 1 ) // 1+ for terminating '\0'
// We need at least one char in addition to terminating '\0'
// because some functions need additional space to detect overflow
ELASTICAPM_STATIC_ASSERT( ELASTICAPM_TEXT_OUTPUT_STREAM_RESERVED_SPACE_SIZE >= 2 );

// 1 for at least one char of content in case of overflow
#define ELASTICAPM_TEXT_OUTPUT_STREAM_MIN_BUFFER_SIZE ( 1 + ELASTICAPM_TEXT_OUTPUT_STREAM_RESERVED_SPACE_SIZE )

#define ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ( 1024 )
ELASTICAPM_STATIC_ASSERT( ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE >= ELASTICAPM_TEXT_OUTPUT_STREAM_MIN_BUFFER_SIZE );

static inline
void assertValidEndPtrIntoTextOutputStream( const char* ptr, const TextOutputStream* txtOutStream )
{
    ELASTICAPM_ASSERT_VALID_PTR( ptr );
    ELASTICAPM_ASSERT_LE_PTR( txtOutStream->bufferBegin, ptr );
    ELASTICAPM_ASSERT_LE_PTR( ptr, txtOutStream->bufferBegin + txtOutStream->bufferSize );
}

static inline
void assertValidBeginPtrIntoTextOutputStream( const char* ptr, const TextOutputStream* txtOutStream )
{
    assertValidEndPtrIntoTextOutputStream( ptr, txtOutStream );
    ELASTICAPM_ASSERT_LT_PTR( ptr, txtOutStream->bufferBegin + txtOutStream->bufferSize );
}

static inline
void assertValidTextOutputStream( const TextOutputStream* txtOutStream )
{
    ELASTICAPM_ASSERT_VALID_PTR( txtOutStream );
    ELASTICAPM_ASSERT_VALID_PTR( txtOutStream->bufferBegin );
    ELASTICAPM_ASSERT_GE_UINT64( txtOutStream->bufferSize, ELASTICAPM_TEXT_OUTPUT_STREAM_MIN_BUFFER_SIZE );
    if ( txtOutStream->isOverflowed )
    {
        assertValidEndPtrIntoTextOutputStream( txtOutStream->freeSpaceBegin, txtOutStream );
    }
    else
    {
        assertValidBeginPtrIntoTextOutputStream( txtOutStream->freeSpaceBegin, txtOutStream );
    }
}

#define ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream ) \
    ELASTICAPM_ASSERT_VALID_OBJ( assertValidTextOutputStream( txtOutStream ) ) \
/**/

static inline
bool textOutputStreamIsOverflowed( TextOutputStream* txtOutStream )
{
    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    return txtOutStream->isOverflowed;
}

static inline
TextOutputStream makeTextOutputStream( char* bufferBegin, size_t bufferSize )
{
    ELASTICAPM_ASSERT_VALID_PTR( bufferBegin );
    ELASTICAPM_ASSERT_GE_UINT64( bufferSize, ELASTICAPM_TEXT_OUTPUT_STREAM_MIN_BUFFER_SIZE );

    TextOutputStream txtOutStream =
    {
        .bufferBegin = bufferBegin,
        .bufferSize = bufferSize,
        .freeSpaceBegin = bufferBegin,
        .isOverflowed = false,
        .autoTermZero = true,
        .shouldEncloseUserString = true
    };
    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( &txtOutStream );

    // We cannot work with a buffer that doesn't have reserved space
    if ( bufferSize < ELASTICAPM_TEXT_OUTPUT_STREAM_MIN_BUFFER_SIZE )
    {
        txtOutStream.isOverflowed = true;

        // If buffer is not zero sized then mark it as empty
        if ( bufferSize != 0 ) *bufferBegin = '\0';

        ELASTICAPM_ASSERT( textOutputStreamIsOverflowed( &txtOutStream ), "" );
    }

    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( &txtOutStream );
    return txtOutStream;
}

#define ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( staticBuffer ) \
    ( makeTextOutputStream( (staticBuffer), ELASTICAPM_STATIC_ARRAY_SIZE( (staticBuffer) ) ) )

static inline
char* textOutputStreamGetFreeSpaceBegin( const TextOutputStream* txtOutStream )
{
    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    return txtOutStream->freeSpaceBegin;
}

static inline
bool textOutputStreamHasReservedSpace( TextOutputStream* txtOutStream )
{
    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    return ( txtOutStream->freeSpaceBegin - txtOutStream->bufferBegin ) + ELASTICAPM_TEXT_OUTPUT_STREAM_RESERVED_SPACE_SIZE <= txtOutStream->bufferSize;
}

static inline
size_t textOutputStreamGetFreeSpaceSize( const TextOutputStream* txtOutStream )
{
    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    const size_t reservedAndUsedSpaceSize =
            ELASTICAPM_TEXT_OUTPUT_STREAM_RESERVED_SPACE_SIZE +
            ( txtOutStream->freeSpaceBegin - txtOutStream->bufferBegin );

    if ( txtOutStream->bufferSize < reservedAndUsedSpaceSize ) return 0;

    return txtOutStream->bufferSize - reservedAndUsedSpaceSize;
}

static inline
bool textOutputStreamStartEntry( TextOutputStream* txtOutStream, TextOutputStreamState* txtOutStreamState )
{
    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );
    ELASTICAPM_ASSERT_VALID_PTR( txtOutStreamState );

    if ( textOutputStreamIsOverflowed( txtOutStream ) ) return false;

    txtOutStreamState->autoTermZero = txtOutStream->autoTermZero;
    txtOutStreamState->freeSpaceBegin = txtOutStream->freeSpaceBegin;

    txtOutStream->autoTermZero = false;

    return true;
}

static inline
String textOutputStreamEndEntryEx(
        const char* const contentEnd,
        const TextOutputStreamState* txtOutStreamStateOnEntryStart,
        TextOutputStream* txtOutStream )
{
    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );
    ELASTICAPM_ASSERT_VALID_PTR( txtOutStreamStateOnEntryStart );
    ELASTICAPM_ASSERT_VALID_OBJ( assertValidEndPtrIntoTextOutputStream( contentEnd, txtOutStream ) );

    txtOutStream->autoTermZero = txtOutStreamStateOnEntryStart->autoTermZero;

    if ( textOutputStreamIsOverflowed( txtOutStream ) )
    {
        // If we didn't write any of this entry's content then we just return the marker 
        if ( contentEnd == txtOutStreamStateOnEntryStart->freeSpaceBegin )
            return ELASTICAPM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;

        // otherwise we return partial content with the overflowed marker appended
        return txtOutStreamStateOnEntryStart->freeSpaceBegin;
    }

    ELASTICAPM_ASSERT( textOutputStreamHasReservedSpace( txtOutStream ), "" );
    if ( txtOutStream->autoTermZero ) *( txtOutStream->freeSpaceBegin++ ) = '\0';

    return txtOutStreamStateOnEntryStart->freeSpaceBegin;
}

static inline
String textOutputStreamEndEntryAsOverflowed( const TextOutputStreamState* txtOutStreamStateOnEntryStart, TextOutputStream* txtOutStream )
{
    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    const char* const contentEnd = txtOutStream->freeSpaceBegin;

    if ( ! textOutputStreamIsOverflowed( txtOutStream ) )
    {
        ELASTICAPM_ASSERT( textOutputStreamHasReservedSpace( txtOutStream ), "" );

        strcpy( txtOutStream->freeSpaceBegin, ELASTICAPM_TEXT_OUTPUT_STREAM_OVERFLOWED_MARKER );
        txtOutStream->freeSpaceBegin += ELASTICAPM_TEXT_OUTPUT_STREAM_OVERFLOWED_MARKER_LENGTH;

        txtOutStream->isOverflowed = true;
        ELASTICAPM_ASSERT( textOutputStreamIsOverflowed( txtOutStream ), "" );
    }

    return textOutputStreamEndEntryEx( contentEnd, txtOutStreamStateOnEntryStart,txtOutStream );
}

static inline
String textOutputStreamEndEntry( const TextOutputStreamState* txtOutStreamStateOnEntryStart, TextOutputStream* txtOutStream )
{
    // If we need to append terminating '\0' but there's no space for it
    // then we consider the stream as overflowed
    if ( txtOutStreamStateOnEntryStart->autoTermZero && ( textOutputStreamGetFreeSpaceSize( txtOutStream ) == 0 ) )
        return textOutputStreamEndEntryAsOverflowed( txtOutStreamStateOnEntryStart, txtOutStream );

    return textOutputStreamEndEntryEx( /* contentEnd: */ txtOutStream->freeSpaceBegin, txtOutStreamStateOnEntryStart,txtOutStream );
}

static inline
StringView textOutputStreamContentAsStringView( TextOutputStream* txtOutStream )
{
    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    return makeStringView( txtOutStream->bufferBegin, txtOutStream->freeSpaceBegin - txtOutStream->bufferBegin );
}

static inline
void textOutputStreamRewind( TextOutputStream* txtOutStream )
{
    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    txtOutStream->freeSpaceBegin = txtOutStream->bufferBegin;
    txtOutStream->isOverflowed = false;
}

static inline
void textOutputStreamSkipNChars( TextOutputStream* txtOutStream, size_t numberCharsToSkip )
{
    ELASTICAPM_ASSERT_GE_UINT64( textOutputStreamGetFreeSpaceSize( txtOutStream ), numberCharsToSkip );

    txtOutStream->freeSpaceBegin += numberCharsToSkip;
}

static inline
void textOutputStreamGoBack( TextOutputStream* txtOutStream, size_t numberCharsToGoBack )
{
    ELASTICAPM_ASSERT_GE_PTR( txtOutStream->freeSpaceBegin, txtOutStream->bufferBegin + numberCharsToGoBack );

    txtOutStream->freeSpaceBegin -= numberCharsToGoBack;
}

static inline
StringView textOutputStreamViewFrom( TextOutputStream* txtOutStream, const char* stringViewBegin )
{
    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );
    ELASTICAPM_ASSERT_LE_PTR( txtOutStream->bufferBegin, stringViewBegin );
    ELASTICAPM_ASSERT_LE_PTR( stringViewBegin, txtOutStream->freeSpaceBegin );

    return makeStringView( stringViewBegin, (size_t)( txtOutStream->freeSpaceBegin - stringViewBegin ) );
}

static inline
String streamStringView( StringView value, TextOutputStream* txtOutStream )
{
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( txtOutStream, &txtOutStreamStateOnEntryStart ) )
        return ELASTICAPM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;

    const size_t freeSizeBeforeWrite = textOutputStreamGetFreeSpaceSize( txtOutStream );

    const size_t numberOfCharsToCopy = ELASTICAPM_MIN( value.length, freeSizeBeforeWrite );
    if ( numberOfCharsToCopy != 0 )
        memcpy( txtOutStreamStateOnEntryStart.freeSpaceBegin, value.begin, numberOfCharsToCopy * sizeof( char ) );
    textOutputStreamSkipNChars( txtOutStream, numberOfCharsToCopy );
    
    if ( numberOfCharsToCopy < value.length )
        return textOutputStreamEndEntryAsOverflowed( &txtOutStreamStateOnEntryStart, txtOutStream );

    return textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, txtOutStream );
}

static inline
String streamVPrintf( TextOutputStream* txtOutStream, String printfFmt, va_list printfFmtArgs )
{
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( txtOutStream, &txtOutStreamStateOnEntryStart ) )
        return ELASTICAPM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;

    const size_t freeSpaceSize = textOutputStreamGetFreeSpaceSize( txtOutStream );

    // We can take one more char from reserved space for printf's terminating '\0'
    int snprintfRetVal = vsnprintf( txtOutStreamStateOnEntryStart.freeSpaceBegin, freeSpaceSize + 1, printfFmt, printfFmtArgs );

    // snprintf: If an encoding error occurs, a negative number is returned
    // so we don't change advance the buffer tracker
    if ( snprintfRetVal < 0 ) return "<vsnprintf returned error>";

    // snprintf: when returned value is non-negative and less than buffer-size
    // then the string has been completely written
    // otherwise it means buffer overflowed
    const bool isOverflowed = ( snprintfRetVal > freeSpaceSize );
    // snprintf always writes terminating '\0'
    // but return value is number of chars written not counting the terminating '\0'
    textOutputStreamSkipNChars( txtOutStream, isOverflowed ? freeSpaceSize : snprintfRetVal );

    return ( isOverflowed )
        ? textOutputStreamEndEntryAsOverflowed( &txtOutStreamStateOnEntryStart, txtOutStream )
        : textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, txtOutStream );
}

// It seems that it's a compilation error to put __attribute__ at function definition
// so we add a seemingly redundant declaration just for __attribute__ ( ( format ( printf, ?, ? ) ) )
static inline
String streamPrintf( TextOutputStream* txtOutStream, String printfFmt, /* printfFmtArgs: */ ... )
        ELASTICAPM_PRINTF_ATTRIBUTE( /* fmtPos: */ 2, /* fmtArgsPos: */ 3 );

static inline
String streamPrintf( TextOutputStream* txtOutStream, String printfFmt, ... )
{
    String retVal = NULL;

    va_list printfFmtArgs;
    va_start( printfFmtArgs, printfFmt );
    retVal = streamVPrintf( txtOutStream, printfFmt, printfFmtArgs );
    va_end( printfFmtArgs );

    return retVal;
}

static inline
String streamInt( int value, TextOutputStream* txtOutStream )
{
    return streamPrintf( txtOutStream, "%d", value );
}

static inline
String streamString( String value, TextOutputStream* txtOutStream )
{
    return streamPrintf( txtOutStream, "%s", value );
}

static inline
String streamBool( bool value, TextOutputStream* txtOutStream )
{
    return streamPrintf( txtOutStream, "%s", boolToString( value ) );
}

static inline
String streamChar( char value, TextOutputStream* txtOutStream )
{
    return streamStringView( makeStringView( &value, 1 ), txtOutStream );
}

static inline
String streamUserString( String value, TextOutputStream* txtOutStream )
{
    return ( value == NULL )
        ? streamStringView( ELASTICAPM_STRING_LITERAL_TO_VIEW( "NULL" ), txtOutStream )
        : streamPrintf( txtOutStream, "`%s'", value );
}

static inline
String streamIndent( unsigned int nestingDepth, TextOutputStream* txtOutStream )
{
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( txtOutStream, &txtOutStreamStateOnEntryStart ) )
        return ELASTICAPM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;

    StringView indent = ELASTICAPM_STRING_LITERAL_TO_VIEW( "    " );

    ELASTICAPM_REPEAT_N_TIMES( nestingDepth ) streamStringView( indent, txtOutStream );

    return textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, txtOutStream );
}
