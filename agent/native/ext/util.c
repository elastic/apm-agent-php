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

#include "util.h"
#include <stdlib.h>
#include "TextOutputStream.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_UTIL

StringView trimStringView( StringView src )
{
    size_t beginIndex = 0;
    size_t endIndex = src.length;
    for ( ; beginIndex < src.length && ( isWhiteSpace( src.begin[ beginIndex ] ) ) ; ++beginIndex );
    for ( ; endIndex > beginIndex && ( isWhiteSpace( src.begin[ endIndex - 1 ] ) ) ; --endIndex );

    return makeStringView( src.begin + beginIndex, endIndex - beginIndex );
}

StringView findEndOfLineSequence( StringView text )
{
    // The order in endOfLineSequences is important because we need to check longer sequences first
    StringView endOfLineSequences[] =
            {
                    ELASTIC_APM_STRING_LITERAL_TO_VIEW( "\r\n" )
                    , ELASTIC_APM_STRING_LITERAL_TO_VIEW( "\n" )
                    , ELASTIC_APM_STRING_LITERAL_TO_VIEW( "\r" )
            };

    ELASTIC_APM_FOR_EACH_INDEX( textPos, text.length )
    {
        ELASTIC_APM_FOR_EACH_INDEX( eolSeqIndex, ELASTIC_APM_STATIC_ARRAY_SIZE( endOfLineSequences ) )
        {
            if ( text.length - textPos < endOfLineSequences[ eolSeqIndex ].length ) continue;

            StringView eolSeqCandidate = makeStringView( &( text.begin[ textPos ] ), endOfLineSequences[ eolSeqIndex ].length );
            if ( areStringViewsEqual( eolSeqCandidate, endOfLineSequences[ eolSeqIndex ] ) )
            {
                return eolSeqCandidate;
            }
        }
    }

    return makeEmptyStringView();
}

bool findCharByPredicate( StringView src, CharPredicate predicate, size_t* foundPosition )
{
    ELASTIC_APM_FOR_EACH_INDEX( pos, src.length )
    {
        if ( predicate( src.begin[ pos ] ) )
        {
            *foundPosition = pos;
            return true;
        }
    }

    return false;
}

ResultCode parseDecimalInteger( StringView inputString, /* out */ Int64* result )
{
    char* pastLastInterpretedChar = NULL;
    StringView trimmedInputString = trimStringView( inputString );
    if ( isEmptyStringView( trimmedInputString ) )
    {
        return resultParsingFailed;
    }

    long parsedNumber = strtol( trimmedInputString.begin, /* out */ &pastLastInterpretedChar, /* base */ 10 );
    if ( pastLastInterpretedChar != stringViewEnd( trimmedInputString ) )
    {
        return resultParsingFailed;
    }
    *result = parsedNumber;
    return resultSuccess;
}

static
ResultCode parseUnits( StringView inputString, StringView unitNames[], size_t numberOfUnits, /* out */ size_t* unitsIndex )
{
    ELASTIC_APM_ASSERT_VALID_PTR( unitsIndex );

    StringView trimmedInputString = trimStringView( inputString );

    ELASTIC_APM_FOR_EACH_INDEX( i, numberOfUnits )
    {
        if ( areStringViewsEqualIgnoringCase( trimmedInputString, unitNames[ i ] ) )
        {
            *unitsIndex = i;
            return resultSuccess;
        }
    }

    return resultParsingFailed;
}

ResultCode parseDecimalIntegerWithUnits( StringView inputString, StringView unitNames[], size_t numberOfUnits, /* out */ Int64* valueInUnits, /* out */ size_t* unitsIndex )
{
    ELASTIC_APM_ASSERT_VALID_PTR( valueInUnits );
    ELASTIC_APM_ASSERT_VALID_PTR( unitsIndex );

    ResultCode resultCode;
    StringView trimmedInputString = trimStringView( inputString );
    size_t firstNotDecimalDigit;
    size_t unitsIndexLocal = numberOfUnits;
    StringView numericPart = trimmedInputString;
    Int64 numericValue;

    if ( findCharByPredicate( trimmedInputString, isNotDecimalDigitOrSign, /* out */ &firstNotDecimalDigit ) )
    {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( parseUnits( subStringView( trimmedInputString, /* offset */ firstNotDecimalDigit ), unitNames, numberOfUnits, /* out */ &unitsIndexLocal ) );
        numericPart = trimStringView( makeStringView( trimmedInputString.begin, firstNotDecimalDigit ) );
    }
    else
    {
        unitsIndexLocal = numberOfUnits;
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( parseDecimalInteger( numericPart, /* out */ &numericValue ) );

    *valueInUnits = numericValue;
    *unitsIndex = unitsIndexLocal;
    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    goto finally;
}

StringView sizeUnitsNames[ numberOfSizeUnits ] =
{
    [ sizeUnits_byte ] = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "B" ),
    [ sizeUnits_kibibyte ] = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "KB" ),
    [ sizeUnits_mebibyte ] = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "MB" ),
    [ sizeUnits_gibibyte ] = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "GB" ),
};

ResultCode parseSize( StringView inputString, SizeUnits defaultUnits, /* out */ Size* result )
{
    ELASTIC_APM_ASSERT_VALID_PTR( result );

    size_t unitsIndex;
    ResultCode resultCode = parseDecimalIntegerWithUnits( inputString, sizeUnitsNames, numberOfSizeUnits, &result->valueInUnits, &unitsIndex );
    if ( resultCode == resultSuccess )
    {
        result->units = ( unitsIndex == numberOfSizeUnits ? defaultUnits : (SizeUnits)unitsIndex );
    }
    return resultCode;
}

String streamSize( Size size, TextOutputStream* txtOutStream )
{
    return isValidSizeUnits( size.units )
        ? streamPrintf( txtOutStream, "%"PRId64"%s", size.valueInUnits, sizeUnitsToString( size.units ) )
        : streamPrintf( txtOutStream, "%"PRId64"<invalid units as int: %d>", size.valueInUnits, size.units );
}

static const Int64 sizeKibiFactor = 1024;

Int64 sizeToBytes( Size size )
{
    switch (size.units)
    {
        case sizeUnits_byte:
            return size.valueInUnits;

        case sizeUnits_kibibyte:
            return size.valueInUnits * sizeKibiFactor;

        case sizeUnits_mebibyte:
            return size.valueInUnits * sizeKibiFactor * sizeKibiFactor;

        case sizeUnits_gibibyte:
            return size.valueInUnits * sizeKibiFactor * sizeKibiFactor * sizeKibiFactor;

        default:
            ELASTIC_APM_ASSERT( false, "Unknown size units (as int): %d", size.units );
            return size.valueInUnits;
    }
}
