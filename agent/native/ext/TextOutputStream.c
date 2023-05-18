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

#include "TextOutputStream.h"

TextOutputStream makeTextOutputStream( char* bufferBegin, size_t bufferSize )
{
    ELASTIC_APM_ASSERT_VALID_PTR( bufferBegin );
    ELASTIC_APM_ASSERT_GE_UINT64( bufferSize, ELASTIC_APM_TEXT_OUTPUT_STREAM_MIN_BUFFER_SIZE );

    TextOutputStream txtOutStream =
            {
                    .bufferBegin = bufferBegin,
                    .bufferSize = bufferSize,
                    .freeSpaceBegin = bufferBegin,
                    .isOverflowed = false,
                    .autoTermZero = true,
                    .shouldEncloseUserString = true
            };
    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( &txtOutStream );

    // We cannot work with a buffer that doesn't have reserved space
    if ( bufferSize < ELASTIC_APM_TEXT_OUTPUT_STREAM_MIN_BUFFER_SIZE )
    {
        txtOutStream.isOverflowed = true;

        // If buffer is not zero sized then mark it as empty
        if ( bufferSize != 0 ) *bufferBegin = '\0';

        ELASTIC_APM_ASSERT( textOutputStreamIsOverflowed( &txtOutStream ), "" );
    }

    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( &txtOutStream );
    return txtOutStream;
}

bool textOutputStreamStartEntry( TextOutputStream* txtOutStream, TextOutputStreamState* txtOutStreamState )
{
    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );
    ELASTIC_APM_ASSERT_VALID_PTR( txtOutStreamState );

    if ( textOutputStreamIsOverflowed( txtOutStream ) ) return false;

    txtOutStreamState->autoTermZero = txtOutStream->autoTermZero;
    txtOutStreamState->freeSpaceBegin = txtOutStream->freeSpaceBegin;

    txtOutStream->autoTermZero = false;

    return true;
}

String streamStringView( StringView value, TextOutputStream* txtOutStream )
{
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( txtOutStream, &txtOutStreamStateOnEntryStart ) )
        return ELASTIC_APM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;

    const size_t freeSizeBeforeWrite = textOutputStreamGetFreeSpaceSize( txtOutStream );

    const size_t numberOfCharsToCopy = ELASTIC_APM_MIN( value.length, freeSizeBeforeWrite );
    if ( numberOfCharsToCopy != 0 )
        memcpy( txtOutStreamStateOnEntryStart.freeSpaceBegin, value.begin, numberOfCharsToCopy * sizeof( char ) );
    textOutputStreamSkipNChars( txtOutStream, numberOfCharsToCopy );

    if ( numberOfCharsToCopy < value.length )
        return textOutputStreamEndEntryAsOverflowed( &txtOutStreamStateOnEntryStart, txtOutStream );

    return textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, txtOutStream );
}

String streamVPrintf( TextOutputStream* txtOutStream, String printfFmt, va_list printfFmtArgs )
{
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( txtOutStream, &txtOutStreamStateOnEntryStart ) )
        return ELASTIC_APM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;

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

size_t textOutputStreamGetFreeSpaceSize( const TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    const size_t reservedAndUsedSpaceSize =
            ELASTIC_APM_TEXT_OUTPUT_STREAM_RESERVED_SPACE_SIZE +
            ( txtOutStream->freeSpaceBegin - txtOutStream->bufferBegin );

    if ( txtOutStream->bufferSize < reservedAndUsedSpaceSize ) return 0;

    return txtOutStream->bufferSize - reservedAndUsedSpaceSize;
}

String textOutputStreamEndEntryAsOverflowed( const TextOutputStreamState* txtOutStreamStateOnEntryStart, TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    const char* const contentEnd = txtOutStream->freeSpaceBegin;

    if ( ! textOutputStreamIsOverflowed( txtOutStream ) )
    {
        ELASTIC_APM_ASSERT( textOutputStreamHasReservedSpace( txtOutStream ), "" );

        strcpy( txtOutStream->freeSpaceBegin, ELASTIC_APM_TEXT_OUTPUT_STREAM_OVERFLOWED_MARKER );
        txtOutStream->freeSpaceBegin += ELASTIC_APM_TEXT_OUTPUT_STREAM_OVERFLOWED_MARKER_LENGTH;

        txtOutStream->isOverflowed = true;
        ELASTIC_APM_ASSERT( textOutputStreamIsOverflowed( txtOutStream ), "" );
    }

    return textOutputStreamEndEntryEx( contentEnd, txtOutStreamStateOnEntryStart,txtOutStream );
}

String textOutputStreamEndEntry( const TextOutputStreamState* txtOutStreamStateOnEntryStart, TextOutputStream* txtOutStream )
{
    // If we need to append terminating '\0' but there's no space for it
    // then we consider the stream as overflowed
    if ( txtOutStreamStateOnEntryStart->autoTermZero && ( textOutputStreamGetFreeSpaceSize( txtOutStream ) == 0 ) )
        return textOutputStreamEndEntryAsOverflowed( txtOutStreamStateOnEntryStart, txtOutStream );

    return textOutputStreamEndEntryEx( /* contentEnd: */ txtOutStream->freeSpaceBegin, txtOutStreamStateOnEntryStart, txtOutStream );
}
