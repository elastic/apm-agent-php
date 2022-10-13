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

#include "TextOutputStream_tests.h"
#include "unit_test_util.h"
#include "elastic_apm_alloc.h"
#include "mock_assert.h"

enum { eachSideGuardSize = 10 };
static const Byte guardByteValue = 0xD8;
static const Byte poisonByteValue = 0xB6;

static
void resetBufferGuards( char* bufferBeginWithoutGuards, size_t bufferSizeWithoutGuards )
{
    char* buffer = bufferBeginWithoutGuards - eachSideGuardSize;
    const size_t bufferSize = bufferSizeWithoutGuards + eachSideGuardSize * 2;

    ELASTIC_APM_FOR_EACH_INDEX( guardByteIndex, eachSideGuardSize )
    {
        buffer[ guardByteIndex ] = guardByteValue;
        buffer[ ( bufferSize - 1 ) - guardByteIndex ] = guardByteValue;
    }

    ELASTIC_APM_FOR_EACH_INDEX( i, bufferSizeWithoutGuards )
        bufferBeginWithoutGuards[ i ] = poisonByteValue;
}

static
void checkBufferGuards( char* bufferBeginWithoutGuards, size_t bufferSizeWithoutGuards )
{
    char* buffer = bufferBeginWithoutGuards - eachSideGuardSize;
    const size_t bufferSize = bufferSizeWithoutGuards + eachSideGuardSize * 2;

    ELASTIC_APM_FOR_EACH_INDEX( guardByteIndex, eachSideGuardSize )
    {
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( (Byte)( buffer[ guardByteIndex ] ), guardByteValue );
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( (Byte)( buffer[ ( bufferSize - 1 ) - guardByteIndex ] ), guardByteValue );
    }
}

static
void checkGuardsAndResetBuffer( char* bufferBeginWithoutGuards, size_t bufferSizeWithoutGuards )
{
    checkBufferGuards( bufferBeginWithoutGuards, bufferSizeWithoutGuards );
    resetBufferGuards( bufferBeginWithoutGuards, bufferSizeWithoutGuards );
}

static
void testStreamXyzOverflowParameterized(
        StreamXyzFunc streamXyz,
        String expectedStreamRetVal,
        bool autoTermZero,
        char* buffer,
        size_t bufferSize )
{
    const size_t expectedStreamRetValLength = strlen( expectedStreamRetVal );
    const size_t sizeUsedEachStep = expectedStreamRetValLength + ( autoTermZero ? 1 : 0 );
    const size_t numberOfTimesFullyFits = ( bufferSize - ELASTIC_APM_TEXT_OUTPUT_STREAM_RESERVED_SPACE_SIZE ) / sizeUsedEachStep;
    ELASTIC_APM_CMOCKA_ASSERT( numberOfTimesFullyFits >= 3 );

    {
        //
        // Use case when streamXyz result fits exactly numberOfTimesFullyFits times into TextOutputStream
        //

        checkGuardsAndResetBuffer( buffer, bufferSize );

        TextOutputStream txtOutStream = makeTextOutputStream(
                buffer,
                numberOfTimesFullyFits * sizeUsedEachStep + ELASTIC_APM_TEXT_OUTPUT_STREAM_RESERVED_SPACE_SIZE );
        ELASTIC_APM_CMOCKA_ASSERT( txtOutStream.bufferSize <= bufferSize );
        txtOutStream.autoTermZero = autoTermZero;

        ELASTIC_APM_FOR_EACH_INDEX( i, numberOfTimesFullyFits + 1 )
        {
            const size_t usedSpaceSize = txtOutStream.freeSpaceBegin - txtOutStream.bufferBegin;
            ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( usedSpaceSize, i * sizeUsedEachStep );

            String actualStreamRetVal = streamXyz( &txtOutStream );
            if ( i < numberOfTimesFullyFits )
            {
                ELASTIC_APM_CMOCKA_ASSERT( ! txtOutStream.isOverflowed );
                if ( autoTermZero )
                    assert_string_equal( actualStreamRetVal, expectedStreamRetVal );
                else
                    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL(
                            makeStringView( actualStreamRetVal, expectedStreamRetValLength ),
                            makeStringView( expectedStreamRetVal, expectedStreamRetValLength ) );
            }
            else
            {
                ELASTIC_APM_CMOCKA_ASSERT( txtOutStream.isOverflowed );
                assert_string_equal( actualStreamRetVal, ELASTIC_APM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER );
            }
        }
    }

    if ( sizeUsedEachStep == 1 ) return;

    {
        //
        // Use case when streamXyz result fits into TextOutputStream ( numberOfTimesFullyFits - 1 ) times fully
        // and one more time just one char
        //

        checkGuardsAndResetBuffer( buffer, bufferSize );

        TextOutputStream txtOutStream = makeTextOutputStream(
                buffer,
                ( numberOfTimesFullyFits - 1 ) * sizeUsedEachStep + 1 + ELASTIC_APM_TEXT_OUTPUT_STREAM_RESERVED_SPACE_SIZE );
        ELASTIC_APM_CMOCKA_ASSERT( txtOutStream.bufferSize <= bufferSize );
        txtOutStream.autoTermZero = autoTermZero;

        ELASTIC_APM_FOR_EACH_INDEX( i, numberOfTimesFullyFits )
        {
            const size_t usedSpaceSize = txtOutStream.freeSpaceBegin - txtOutStream.bufferBegin;
            ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( usedSpaceSize, i * sizeUsedEachStep );

            String actualStreamRetVal = streamXyz( &txtOutStream );
            if ( i < ( numberOfTimesFullyFits - 1 ) )
            {
                ELASTIC_APM_CMOCKA_ASSERT( ! txtOutStream.isOverflowed );
                if ( autoTermZero )
                    assert_string_equal( actualStreamRetVal, expectedStreamRetVal );
                else
                    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL(
                            makeStringView( actualStreamRetVal, expectedStreamRetValLength ),
                            makeStringView( expectedStreamRetVal, expectedStreamRetValLength ) );
            }
            else
            {
                ELASTIC_APM_CMOCKA_ASSERT( txtOutStream.isOverflowed );
                char expectedPartialTxtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
                TextOutputStream expectedPartialTxtOutStream =
                        ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( expectedPartialTxtOutStreamBuf );
                String expectedPartialStreamRetVal = streamPrintf(
                        &expectedPartialTxtOutStream,
                        "%c%s",
                        expectedStreamRetVal[ 0 ], ELASTIC_APM_TEXT_OUTPUT_STREAM_OVERFLOWED_MARKER );
                assert_string_equal( actualStreamRetVal, expectedPartialStreamRetVal );
            }
        }
    }

    {
        //
        // Use case when streamXyz result fits into TextOutputStream ( numberOfTimesFullyFits - 1 ) times fully
        // and one more time almost fully but being just one char short of buffer space
        //

        checkGuardsAndResetBuffer( buffer, bufferSize );

        TextOutputStream txtOutStream = makeTextOutputStream(
                buffer,
                ( numberOfTimesFullyFits - 1 ) * sizeUsedEachStep + ( sizeUsedEachStep - 1 ) + ELASTIC_APM_TEXT_OUTPUT_STREAM_RESERVED_SPACE_SIZE );
        ELASTIC_APM_CMOCKA_ASSERT( txtOutStream.bufferSize <= bufferSize );
        txtOutStream.autoTermZero = autoTermZero;

        ELASTIC_APM_FOR_EACH_INDEX( i, (numberOfTimesFullyFits - 1) )
        {
            const size_t usedSpaceSize = txtOutStream.freeSpaceBegin - txtOutStream.bufferBegin;
            ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( usedSpaceSize, i * sizeUsedEachStep );
            String actualStreamRetVal = streamXyz( &txtOutStream );
            if ( i < ( numberOfTimesFullyFits - 1 ) )
            {
                ELASTIC_APM_CMOCKA_ASSERT( ! txtOutStream.isOverflowed );
                if ( autoTermZero )
                {
                    ELASTIC_APM_CMOCKA_ASSERT_STRING_EQUAL(
                            actualStreamRetVal,
                            expectedStreamRetVal,
                            "numberOfTimesFullyFits: %u. i: %u. sizeUsedEachStep: %u. autoTermZero: %s.",
                            (UInt)numberOfTimesFullyFits, (UInt)i, (UInt)sizeUsedEachStep, boolToString( autoTermZero ) );
                }
                else
                {
                    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL(
                            makeStringView( actualStreamRetVal, expectedStreamRetValLength ),
                            makeStringView( expectedStreamRetVal, expectedStreamRetValLength ) );
                }
            }
            else
            {
                ELASTIC_APM_CMOCKA_ASSERT( txtOutStream.isOverflowed );

                char expectedPartialTxtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
                TextOutputStream expectedPartialTxtOutStream =
                        ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( expectedPartialTxtOutStreamBuf );
                TextOutputStreamState expectedPartialTxtOutStreamStateOnEntryStart;
                bool startRetVal = textOutputStreamStartEntry( &expectedPartialTxtOutStream, &expectedPartialTxtOutStreamStateOnEntryStart );
                ELASTIC_APM_CMOCKA_ASSERT( startRetVal );
                streamStringView( makeStringView( expectedStreamRetVal, expectedStreamRetValLength - 1 ), &expectedPartialTxtOutStream );
                streamString( ELASTIC_APM_TEXT_OUTPUT_STREAM_OVERFLOWED_MARKER, &expectedPartialTxtOutStream );
                String expectedPartialStreamRetVal = textOutputStreamEndEntry( &expectedPartialTxtOutStreamStateOnEntryStart, &expectedPartialTxtOutStream );

                assert_string_equal( actualStreamRetVal, expectedPartialStreamRetVal );
            }
        }
    }
}

void testStreamXyzOverflow( StreamXyzFunc streamXyz )
{
    ResultCode resultCode;

    String streamXyzRetVal;
    char streamXyzRetValTxtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream streamXyzRetValTxtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( streamXyzRetValTxtOutStreamBuf );
    streamXyzRetVal = streamXyz( &streamXyzRetValTxtOutStream );
    assert_ptr_equal( streamXyzRetValTxtOutStreamBuf, streamXyzRetVal );
    ELASTIC_APM_CMOCKA_ASSERT( strlen( streamXyzRetVal ) > 0 );

    enum { minNumberOfTimesFullyFits = 3 };
    ELASTIC_APM_STATIC_ASSERT( minNumberOfTimesFullyFits >= 3 );

    // +1 for terminating '\0'
    const size_t bufferSizeWithoutGuards =
            minNumberOfTimesFullyFits * ( strlen( streamXyzRetVal ) + 1 ) +
            ELASTIC_APM_TEXT_OUTPUT_STREAM_RESERVED_SPACE_SIZE;
    const size_t bufferSize = bufferSizeWithoutGuards + eachSideGuardSize * 2;
    char* buffer = NULL;
    ELASTIC_APM_PEMALLOC_STRING_IF_FAILED_GOTO( bufferSize, buffer );
    char* bufferBeginWithoutGuards = buffer + eachSideGuardSize;
    resetBufferGuards( bufferBeginWithoutGuards, bufferSizeWithoutGuards );

    bool autoTermZeroValues[] = { true, false };
    ELASTIC_APM_FOR_EACH_INDEX( autoTermZeroValuesIndex, ELASTIC_APM_STATIC_ARRAY_SIZE( autoTermZeroValues ) )
    {
        testStreamXyzOverflowParameterized(
                streamXyz,
                streamXyzRetVal,
                autoTermZeroValues[ autoTermZeroValuesIndex ],
                bufferBeginWithoutGuards,
                bufferSizeWithoutGuards );

        checkBufferGuards( bufferBeginWithoutGuards, bufferSizeWithoutGuards );
    }

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( resultCode );
    ELASTIC_APM_PEFREE_STRING_SIZE_AND_SET_TO_NULL( bufferSize, buffer );
    return;

    failure:
    goto finally;
}

static
void buffer_size_below_min_parameterized_assert_after_attempt(
        TextOutputStream* txtOutStream,
        size_t bufferSizeForTextOutputStream,
        char* buffer,
        size_t bufferSize )
{
    char* bufferBeginWithoutGuards = buffer == NULL ? NULL : ( buffer + eachSideGuardSize );
    size_t bufferSizeWithoutGuards = bufferSize - eachSideGuardSize * 2;

    ELASTIC_APM_CMOCKA_ASSERT( getProductionCodeAssertFailedCount() > 0 );
    ELASTIC_APM_CMOCKA_ASSERT( txtOutStream->isOverflowed );

    if ( buffer == NULL ) return;

    checkBufferGuards( bufferBeginWithoutGuards, bufferSizeWithoutGuards );

    if ( bufferSizeForTextOutputStream != 0 )
        ELASTIC_APM_CMOCKA_ASSERT_CHAR_EQUAL( *bufferBeginWithoutGuards, '\0' );

    ELASTIC_APM_FOR_EACH_INDEX( i, bufferSize - ( bufferSizeForTextOutputStream + 2 * eachSideGuardSize ) )
        ELASTIC_APM_CMOCKA_ASSERT_CHAR_EQUAL( *( bufferBeginWithoutGuards + bufferSizeForTextOutputStream + i ), poisonByteValue );
}

static
void buffer_size_below_min_parameterized( size_t bufferSizeForTextOutputStream, char* buffer, size_t bufferSize )
{
    ELASTIC_APM_CMOCKA_ASSERT( bufferSizeForTextOutputStream < ELASTIC_APM_TEXT_OUTPUT_STREAM_MIN_BUFFER_SIZE );
    ELASTIC_APM_CMOCKA_ASSERT( buffer == NULL ? ( bufferSize == 0 ) : ( bufferSize >= 1 + eachSideGuardSize * 2 ) );

    char* bufferBeginWithoutGuards = buffer == NULL ? NULL : ( buffer + eachSideGuardSize );
    size_t bufferSizeWithoutGuards = bufferSize - 2 * eachSideGuardSize;

    if ( buffer != NULL ) resetBufferGuards( bufferBeginWithoutGuards, bufferSizeWithoutGuards );

    resetProductionCodeAssertFailedCount();
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( getProductionCodeAssertFailedCount(), 0 );
    TextOutputStream txtOutStream = makeTextOutputStream( bufferBeginWithoutGuards, bufferSizeForTextOutputStream );
    buffer_size_below_min_parameterized_assert_after_attempt( &txtOutStream, bufferSizeForTextOutputStream, buffer, bufferSize );

    resetProductionCodeAssertFailedCount();
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( getProductionCodeAssertFailedCount(), 0 );
    streamInt( 123, &txtOutStream );
    buffer_size_below_min_parameterized_assert_after_attempt( &txtOutStream, bufferSizeForTextOutputStream, buffer, bufferSize );

    resetProductionCodeAssertFailedCount();
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( getProductionCodeAssertFailedCount(), 0 );
    streamStringView( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "abc" ), &txtOutStream );
    ELASTIC_APM_CMOCKA_ASSERT( getProductionCodeAssertFailedCount() > 0 );
    buffer_size_below_min_parameterized_assert_after_attempt( &txtOutStream, bufferSizeForTextOutputStream, buffer, bufferSize );
}

static
void buffer_size_below_min( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    setProductionCodeAssertFailed( productionCodeAssertFailedCountingMock );

    char buffer[ ELASTIC_APM_TEXT_OUTPUT_STREAM_MIN_BUFFER_SIZE + 2 * eachSideGuardSize ];

    buffer_size_below_min_parameterized( 0, NULL, 0 );
    buffer_size_below_min_parameterized( 0, buffer, ELASTIC_APM_STATIC_ARRAY_SIZE( buffer ) );
    buffer_size_below_min_parameterized( 1, buffer, ELASTIC_APM_STATIC_ARRAY_SIZE( buffer ) );
    buffer_size_below_min_parameterized( ELASTIC_APM_TEXT_OUTPUT_STREAM_MIN_BUFFER_SIZE - 1, buffer, ELASTIC_APM_STATIC_ARRAY_SIZE( buffer ) );
}

static
void assert_number_of_chars_written( size_t expectedNumberOfCharsWritten, const TextOutputStream* txtOutStream )
{
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( textOutputStreamGetFreeSpaceSize( txtOutStream ),
            ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE -
            ELASTIC_APM_TEXT_OUTPUT_STREAM_RESERVED_SPACE_SIZE -
            expectedNumberOfCharsWritten );

    assert_ptr_equal( textOutputStreamGetFreeSpaceBegin( txtOutStream ), txtOutStream->bufferBegin + expectedNumberOfCharsWritten );
}

static
void stream_string_helper(
        TextOutputStream* txtOutStream,
        size_t* expectedNumberOfCharsWritten,
        const char* stringToStream )
{
    assert_string_equal( streamString( stringToStream, txtOutStream ), stringToStream );
    *expectedNumberOfCharsWritten += strlen( stringToStream ) + 1;
    assert_number_of_chars_written( *expectedNumberOfCharsWritten, txtOutStream );
}

static
void stream_string( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    size_t expectedNumberOfCharsWritten = 0;

    stream_string_helper( &txtOutStream, &expectedNumberOfCharsWritten, "" );
    stream_string_helper( &txtOutStream, &expectedNumberOfCharsWritten, "1" );
    stream_string_helper( &txtOutStream, &expectedNumberOfCharsWritten, "abc" );

    const char expectedMem[] = "" "\0" "1" "\0" "abc";
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_STATIC_ARRAY_SIZE( expectedMem ), expectedNumberOfCharsWritten );
    assert_memory_equal( txtOutStream.bufferBegin, expectedMem, expectedNumberOfCharsWritten );
}

static
void stream_string_no_auto_term_zero_helper(
        TextOutputStream* txtOutStream,
        size_t* expectedNumberOfCharsWritten,
        const char* stringToStream )
{
    const size_t stringToStreamLen = strlen( stringToStream );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL(
            makeStringView( streamString( stringToStream, txtOutStream ), stringToStreamLen ),
            makeStringView( stringToStream, stringToStreamLen ) );
    *expectedNumberOfCharsWritten += stringToStreamLen;
    assert_number_of_chars_written( *expectedNumberOfCharsWritten, txtOutStream );
}

static void stream_string_no_auto_term_zero( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    txtOutStream.autoTermZero = false;
    size_t expectedNumberOfCharsWritten = 0;

    stream_string_no_auto_term_zero_helper( &txtOutStream, &expectedNumberOfCharsWritten, "" );
    stream_string_no_auto_term_zero_helper( &txtOutStream, &expectedNumberOfCharsWritten, "1" );
    stream_string_no_auto_term_zero_helper( &txtOutStream, &expectedNumberOfCharsWritten, "abc" );

    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL(
            makeStringView( txtOutStreamBuf, expectedNumberOfCharsWritten ),
            "" "1" "abc" );
}

static
String streamStringUnderOverflowTest( TextOutputStream* txtOutStream )
{
    return streamString( "ABC", txtOutStream );
}

static
void stream_string_overflow( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    testStreamXyzOverflow( streamStringUnderOverflowTest );
}

static
void stream_int_helper(
        TextOutputStream* txtOutStream,
        size_t* expectedNumberOfCharsWritten,
        int intToStream,
        const char* expectedIntAsString )
{
    assert_string_equal( streamInt( intToStream, txtOutStream ), expectedIntAsString );
    *expectedNumberOfCharsWritten += strlen( expectedIntAsString ) + 1;
    assert_number_of_chars_written( *expectedNumberOfCharsWritten, txtOutStream );
}

static
void stream_int( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    size_t expectedNumberOfCharsWritten = 0;

    stream_int_helper( &txtOutStream, &expectedNumberOfCharsWritten, 0, "0" );
    stream_int_helper( &txtOutStream, &expectedNumberOfCharsWritten, 123, "123" );
    stream_int_helper( &txtOutStream, &expectedNumberOfCharsWritten, -1, "-1" );
    stream_int_helper( &txtOutStream, &expectedNumberOfCharsWritten, 2147483647, "2147483647" );
    // We do (int)(-2147483648LL) to avoid warning C4146: unary minus operator applied to unsigned type, result still unsigned
    stream_int_helper( &txtOutStream, &expectedNumberOfCharsWritten, -2147483648LL, "-2147483648" );

    const char expectedMem[] = "0" "\0" "123" "\0" "-1" "\0" "2147483647" "\0" "-2147483648" "\0";
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_STATIC_ARRAY_SIZE( expectedMem ) - 1, expectedNumberOfCharsWritten );
    assert_memory_equal( txtOutStream.bufferBegin, expectedMem, expectedNumberOfCharsWritten );
}

static
void stream_int_no_auto_term_zero_helper(
        TextOutputStream* txtOutStream,
        size_t* expectedNumberOfCharsWritten,
        int intToStream,
        const char* expectedIntAsString )
{
    const size_t expectedIntAsStringLen = strlen( expectedIntAsString );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL(
            makeStringView( streamInt( intToStream, txtOutStream ), expectedIntAsStringLen ),
            makeStringView( expectedIntAsString, expectedIntAsStringLen ) );
    *expectedNumberOfCharsWritten += expectedIntAsStringLen;
    assert_number_of_chars_written( *expectedNumberOfCharsWritten, txtOutStream );
}

static
void stream_int_no_auto_term_zero( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    txtOutStream.autoTermZero = false;
    size_t expectedNumberOfCharsWritten = 0;

    stream_int_no_auto_term_zero_helper( &txtOutStream, &expectedNumberOfCharsWritten, 0, "0" );
    stream_int_no_auto_term_zero_helper( &txtOutStream, &expectedNumberOfCharsWritten, 123, "123" );
    stream_int_no_auto_term_zero_helper( &txtOutStream, &expectedNumberOfCharsWritten, -1, "-1" );
    stream_int_no_auto_term_zero_helper( &txtOutStream, &expectedNumberOfCharsWritten, 2147483647, "2147483647" );
    // We do (int)(-2147483648LL) to avoid warning C4146: unary minus operator applied to unsigned type, result still unsigned
    stream_int_no_auto_term_zero_helper( &txtOutStream, &expectedNumberOfCharsWritten, -2147483648LL, "-2147483648" );

    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL(
            makeStringView( txtOutStreamBuf, expectedNumberOfCharsWritten ),
            "0" "123" "-1" "2147483647" "-2147483648" );
}

static
String streamIntUnderOverflowTest( TextOutputStream* txtOutStream )
{
    return streamInt( 4321, txtOutStream );
}

static
void stream_int_overflow( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    testStreamXyzOverflow( streamIntUnderOverflowTest );
}

static
String streamCharUnderOverflowTest( TextOutputStream* txtOutStream )
{
    return streamChar( '#', txtOutStream );
}

static
void stream_char_overflow( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    testStreamXyzOverflow( streamCharUnderOverflowTest );
}

static
void stream_StringView_helper(
        TextOutputStream* txtOutStream,
        size_t* expectedNumberOfCharsWritten,
        StringView stringViewToStream )
{
    String streamStringViewRetVal = streamStringView( stringViewToStream, txtOutStream );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL(
            makeStringViewFromString( streamStringViewRetVal ),
            stringViewToStream );
    *expectedNumberOfCharsWritten += stringViewToStream.length + 1;
    assert_number_of_chars_written( *expectedNumberOfCharsWritten, txtOutStream );
}

static
void stream_StringView( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    size_t expectedNumberOfCharsWritten = 0;

    #define ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT "some text"
    stream_StringView_helper(
            &txtOutStream,
            &expectedNumberOfCharsWritten,
            makeStringView(
                    ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT "| suffix that is not part of StringView",
                    strlen( ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT ) ) );
    #undef ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT

    #define ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT ""
    stream_StringView_helper(
            &txtOutStream,
            &expectedNumberOfCharsWritten,
            makeStringView(
                    ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT "| suffix that is not part of StringView",
                    strlen( ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT ) ) );
    #undef ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT

    #define ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT "some more text"
    stream_StringView_helper(
            &txtOutStream,
            &expectedNumberOfCharsWritten,
            makeStringView(
                    ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT "| suffix that is not part of StringView",
                    strlen( ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT ) ) );
    #undef ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT

    const char expectedMem[] = "some text" "\0" "" "\0" "some more text" "\0";
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_STATIC_ARRAY_SIZE( expectedMem ) - 1, expectedNumberOfCharsWritten );
    assert_memory_equal( txtOutStream.bufferBegin, expectedMem, expectedNumberOfCharsWritten );
}

static
void stream_StringView_no_auto_term_helper(
        TextOutputStream* txtOutStream,
        size_t* expectedNumberOfCharsWritten,
        StringView stringViewToStream )
{
    String streamStringViewRetVal = streamStringView( stringViewToStream, txtOutStream );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL(
            makeStringView( streamStringViewRetVal, stringViewToStream.length ),
            stringViewToStream );
    *expectedNumberOfCharsWritten += stringViewToStream.length;
    assert_number_of_chars_written( *expectedNumberOfCharsWritten, txtOutStream );
}

static void stream_StringView_no_auto_term( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    txtOutStream.autoTermZero = false;
    size_t expectedNumberOfCharsWritten = 0;

    #define ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT "some text"
    stream_StringView_no_auto_term_helper(
            &txtOutStream,
            &expectedNumberOfCharsWritten,
            makeStringView(
                    ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT "| suffix that is not part of StringView",
                    strlen( ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT ) ) );
    #undef ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT

    #define ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT ""
    stream_StringView_no_auto_term_helper(
            &txtOutStream,
            &expectedNumberOfCharsWritten,
            makeStringView(
                    ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT "| suffix that is not part of StringView",
                    strlen( ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT ) ) );
    #undef ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT

    #define ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT "some more text"
    stream_StringView_no_auto_term_helper(
            &txtOutStream,
            &expectedNumberOfCharsWritten,
            makeStringView(
                    ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT "| suffix that is not part of StringView",
                    strlen( ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT ) ) );
    #undef ELASTIC_APM_STREAM_STRING_VIEW_TEST_TEXT

    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL(
            makeStringView( txtOutStreamBuf, expectedNumberOfCharsWritten ),
            "some text" "" "some more text" );
}

static void term_zero_in_stream( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    size_t expectedNumberOfCharsWritten = 0;

    stream_int_helper( &txtOutStream, &expectedNumberOfCharsWritten, 987654321, "987654321" );

    assert_string_equal( streamChar( '\0', &txtOutStream ), "" );
    expectedNumberOfCharsWritten += 1 + 1;
    assert_number_of_chars_written( expectedNumberOfCharsWritten, &txtOutStream );

    const char expectedMem[] = "987654321" "\0" "\0" "\0";
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_STATIC_ARRAY_SIZE( expectedMem ) - 1, expectedNumberOfCharsWritten );
    assert_memory_equal( txtOutStream.bufferBegin, expectedMem, expectedNumberOfCharsWritten );
}

static void term_zero_in_stream_no_auto_term( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    txtOutStream.autoTermZero = false;
    size_t expectedNumberOfCharsWritten = 0;

    stream_string_no_auto_term_zero_helper( &txtOutStream, &expectedNumberOfCharsWritten, "abc" );

    streamChar( '\0', &txtOutStream );
    expectedNumberOfCharsWritten += 1;
    assert_number_of_chars_written( expectedNumberOfCharsWritten, &txtOutStream );

    const char expectedMem[] = "abc" "\0";
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_STATIC_ARRAY_SIZE( expectedMem ) - 1, expectedNumberOfCharsWritten );
    assert_memory_equal( txtOutStream.bufferBegin, expectedMem, expectedNumberOfCharsWritten );
}

static
void stream_printf_helper(
        TextOutputStream* txtOutStream,
        size_t* expectedNumberOfCharsWritten,
        const char* actualStreamRetVal,
        const char* expectedStreamRetVal )
{
    assert_string_equal( actualStreamRetVal, expectedStreamRetVal );
    *expectedNumberOfCharsWritten += strlen( expectedStreamRetVal ) + 1;
    assert_number_of_chars_written( *expectedNumberOfCharsWritten, txtOutStream );
}

static
void stream_printf( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    size_t expectedNumberOfCharsWritten = 0;

    stream_printf_helper( &txtOutStream, &expectedNumberOfCharsWritten,
            streamPrintf( &txtOutStream, "some number: %u and %s", 1234567890, "some text" ),
            "some number: 1234567890 and some text" );

    stream_printf_helper( &txtOutStream, &expectedNumberOfCharsWritten,
            streamPrintf( &txtOutStream, "%s", "" ),
            "" );

    stream_printf_helper( &txtOutStream, &expectedNumberOfCharsWritten,
            streamPrintf( &txtOutStream, "another number: %d and %s", -987654321, "some more text" ),
            "another number: -987654321 and some more text" );

    const char expectedMem[] = "some number: 1234567890 and some text" "\0" "" "\0" "another number: -987654321 and some more text" "\0";
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( ELASTIC_APM_STATIC_ARRAY_SIZE( expectedMem ) - 1, expectedNumberOfCharsWritten );
    assert_memory_equal( txtOutStream.bufferBegin, expectedMem, expectedNumberOfCharsWritten );
}

static
void stream_printf_no_auto_term_helper(
        TextOutputStream* txtOutStream,
        size_t* expectedNumberOfCharsWritten,
        const char* actualStreamRetVal,
        const char* expectedStreamRetVal )
{
    const size_t expectedStreamRetValLen = strlen(expectedStreamRetVal );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL(
            makeStringView( actualStreamRetVal, expectedStreamRetValLen ),
            makeStringView( expectedStreamRetVal, expectedStreamRetValLen ) );
    *expectedNumberOfCharsWritten += expectedStreamRetValLen;
    assert_number_of_chars_written( *expectedNumberOfCharsWritten, txtOutStream );
}

static
void stream_printf_no_auto_term( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ELASTIC_APM_UNUSED( testFixtureState );

    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    txtOutStream.autoTermZero = false;
    size_t expectedNumberOfCharsWritten = 0;

    stream_printf_no_auto_term_helper( &txtOutStream, &expectedNumberOfCharsWritten,
            streamPrintf( &txtOutStream, "some number: %u and %s", 1234567890, "some text" ),
            "some number: 1234567890 and some text" );

    stream_printf_no_auto_term_helper( &txtOutStream, &expectedNumberOfCharsWritten,
            streamPrintf( &txtOutStream, "%s", "" ),
            "" );

    stream_printf_no_auto_term_helper( &txtOutStream, &expectedNumberOfCharsWritten,
            streamPrintf( &txtOutStream, "another number: %d and %s", -987654321, "some more text" ),
            "another number: -987654321 and some more text" );

    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL(
            makeStringView( txtOutStreamBuf, expectedNumberOfCharsWritten ), "some number: 1234567890 and some text" "" "another number: -987654321 and some more text" );
}

static
String streamPrintfUnderOverflowTest( TextOutputStream* txtOutStream )
{
    return streamPrintf( txtOutStream, "some number: %u and %s", 1234567890, "some text" );
}

static
void stream_printf_overflow( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    testStreamXyzOverflow( streamPrintfUnderOverflowTest );
}

int run_TextOutputStream_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( buffer_size_below_min ),

        ELASTIC_APM_CMOCKA_UNIT_TEST( stream_string ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( stream_string_no_auto_term_zero ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( stream_string_overflow ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( stream_int ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( stream_int_no_auto_term_zero ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( stream_int_overflow ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( stream_char_overflow ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( stream_StringView ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( stream_StringView_no_auto_term ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( term_zero_in_stream ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( term_zero_in_stream_no_auto_term ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( stream_printf ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( stream_printf_no_auto_term ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( stream_printf_overflow ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
