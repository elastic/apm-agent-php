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
#include <stdio.h>
#include <string.h>
#include <stdarg.h>
#include <inttypes.h>
#include "elastic_apm_assert.h"
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

#define ELASTIC_APM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER "<NOT ENOUGH SPACE in TextOutputStream>"

#define ELASTIC_APM_TEXT_OUTPUT_STREAM_OVERFLOWED_MARKER "..." ELASTIC_APM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER
#define ELASTIC_APM_TEXT_OUTPUT_STREAM_OVERFLOWED_MARKER_LENGTH ( sizeof( ELASTIC_APM_TEXT_OUTPUT_STREAM_OVERFLOWED_MARKER ) - 1 )

#define ELASTIC_APM_TEXT_OUTPUT_STREAM_RESERVED_SPACE_SIZE ( ELASTIC_APM_TEXT_OUTPUT_STREAM_OVERFLOWED_MARKER_LENGTH + 1 ) // 1+ for terminating '\0'
// We need at least one char in addition to terminating '\0'
// because some functions need additional space to detect overflow
ELASTIC_APM_STATIC_ASSERT( ELASTIC_APM_TEXT_OUTPUT_STREAM_RESERVED_SPACE_SIZE >= 2 );

// 1 for at least one char of content in case of overflow
#define ELASTIC_APM_TEXT_OUTPUT_STREAM_MIN_BUFFER_SIZE ( 1 + ELASTIC_APM_TEXT_OUTPUT_STREAM_RESERVED_SPACE_SIZE )

#define ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ( 1024 )
ELASTIC_APM_STATIC_ASSERT( ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE >= ELASTIC_APM_TEXT_OUTPUT_STREAM_MIN_BUFFER_SIZE );

static inline
void assertValidEndPtrIntoTextOutputStream( const char* ptr, const TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR( ptr );
    ELASTIC_APM_ASSERT_LE_PTR( txtOutStream->bufferBegin, ptr );
    ELASTIC_APM_ASSERT_LE_PTR( ptr, txtOutStream->bufferBegin + txtOutStream->bufferSize );
}

static inline
void assertValidBeginPtrIntoTextOutputStream( const char* ptr, const TextOutputStream* txtOutStream )
{
    assertValidEndPtrIntoTextOutputStream( ptr, txtOutStream );
    ELASTIC_APM_ASSERT_LT_PTR( ptr, txtOutStream->bufferBegin + txtOutStream->bufferSize );
}

static inline
void assertValidTextOutputStream( const TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR( txtOutStream );
    ELASTIC_APM_ASSERT_VALID_PTR( txtOutStream->bufferBegin );
    ELASTIC_APM_ASSERT_GE_UINT64( txtOutStream->bufferSize, ELASTIC_APM_TEXT_OUTPUT_STREAM_MIN_BUFFER_SIZE );
    if ( txtOutStream->isOverflowed )
    {
        assertValidEndPtrIntoTextOutputStream( txtOutStream->freeSpaceBegin, txtOutStream );
    }
    else
    {
        assertValidBeginPtrIntoTextOutputStream( txtOutStream->freeSpaceBegin, txtOutStream );
    }
}

#define ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream ) \
    ELASTIC_APM_ASSERT_VALID_OBJ( assertValidTextOutputStream( txtOutStream ) ) \
/**/

static inline
bool textOutputStreamIsOverflowed( TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    return txtOutStream->isOverflowed;
}

TextOutputStream makeTextOutputStream( char* bufferBegin, size_t bufferSize );

#define ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( staticBuffer ) \
    ( makeTextOutputStream( (staticBuffer), ELASTIC_APM_STATIC_ARRAY_SIZE( (staticBuffer) ) ) )

static inline
char* textOutputStreamGetFreeSpaceBegin( const TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    return txtOutStream->freeSpaceBegin;
}

static inline
bool textOutputStreamHasReservedSpace( TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    return ( txtOutStream->freeSpaceBegin - txtOutStream->bufferBegin ) + ELASTIC_APM_TEXT_OUTPUT_STREAM_RESERVED_SPACE_SIZE <= txtOutStream->bufferSize;
}

size_t textOutputStreamGetFreeSpaceSize( const TextOutputStream* txtOutStream );

bool textOutputStreamStartEntry( TextOutputStream* txtOutStream, TextOutputStreamState* txtOutStreamState );

static inline
String textOutputStreamEndEntryEx(
        const char* const contentEnd,
        const TextOutputStreamState* txtOutStreamStateOnEntryStart,
        TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );
    ELASTIC_APM_ASSERT_VALID_PTR( txtOutStreamStateOnEntryStart );
    ELASTIC_APM_ASSERT_VALID_OBJ( assertValidEndPtrIntoTextOutputStream( contentEnd, txtOutStream ) );

    txtOutStream->autoTermZero = txtOutStreamStateOnEntryStart->autoTermZero;

    if ( textOutputStreamIsOverflowed( txtOutStream ) )
    {
        // If we didn't write any of this entry's content then we just return the marker 
        if ( contentEnd == txtOutStreamStateOnEntryStart->freeSpaceBegin )
            return ELASTIC_APM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;

        // otherwise we return partial content with the overflowed marker appended
        return txtOutStreamStateOnEntryStart->freeSpaceBegin;
    }

    ELASTIC_APM_ASSERT( textOutputStreamHasReservedSpace( txtOutStream ), "" );
    if ( txtOutStream->autoTermZero ) *( txtOutStream->freeSpaceBegin++ ) = '\0';

    return txtOutStreamStateOnEntryStart->freeSpaceBegin;
}

String textOutputStreamEndEntryAsOverflowed( const TextOutputStreamState* txtOutStreamStateOnEntryStart, TextOutputStream* txtOutStream );

String textOutputStreamEndEntry( const TextOutputStreamState* txtOutStreamStateOnEntryStart, TextOutputStream* txtOutStream );

static inline
StringView textOutputStreamContentAsStringView( TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    return makeStringView( txtOutStream->bufferBegin, txtOutStream->freeSpaceBegin - txtOutStream->bufferBegin );
}

static inline
void textOutputStreamRewind( TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    txtOutStream->freeSpaceBegin = txtOutStream->bufferBegin;
    txtOutStream->isOverflowed = false;
}

static inline
void textOutputStreamSkipNChars( TextOutputStream* txtOutStream, size_t numberCharsToSkip )
{
    ELASTIC_APM_ASSERT_GE_UINT64( textOutputStreamGetFreeSpaceSize( txtOutStream ), numberCharsToSkip );

    txtOutStream->freeSpaceBegin += numberCharsToSkip;
}

static inline
void textOutputStreamGoBack( TextOutputStream* txtOutStream, size_t numberCharsToGoBack )
{
    ELASTIC_APM_ASSERT_GE_PTR( txtOutStream->freeSpaceBegin, txtOutStream->bufferBegin + numberCharsToGoBack );

    txtOutStream->freeSpaceBegin -= numberCharsToGoBack;
}

static inline
StringView textOutputStreamViewFrom( TextOutputStream* txtOutStream, const char* stringViewBegin )
{
    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );
    ELASTIC_APM_ASSERT_LE_PTR( txtOutStream->bufferBegin, stringViewBegin );
    ELASTIC_APM_ASSERT_LE_PTR( stringViewBegin, txtOutStream->freeSpaceBegin );

    return makeStringView( stringViewBegin, (size_t)( txtOutStream->freeSpaceBegin - stringViewBegin ) );
}

String streamStringView( StringView value, TextOutputStream* txtOutStream );

String streamVPrintf( TextOutputStream* txtOutStream, String printfFmt, va_list printfFmtArgs );

// It seems that it's a compilation error to put __attribute__ at function definition
// so we add a seemingly redundant declaration just for __attribute__ ( ( format ( printf, ?, ? ) ) )
static inline
String streamPrintf( TextOutputStream* txtOutStream, String printfFmt, /* printfFmtArgs: */ ... )
        ELASTIC_APM_PRINTF_ATTRIBUTE( /* fmtPos: */ 2, /* fmtArgsPos: */ 3 );

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
        ? streamStringView( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "NULL" ), txtOutStream )
        : streamPrintf( txtOutStream, "`%s'", value );
}

static inline
String streamIndent( unsigned int nestingDepth, TextOutputStream* txtOutStream )
{
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( txtOutStream, &txtOutStreamStateOnEntryStart ) )
        return ELASTIC_APM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;

    StringView indent = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "    " );

    ELASTIC_APM_REPEAT_N_TIMES( nestingDepth ) streamStringView( indent, txtOutStream );

    return textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, txtOutStream );
}
