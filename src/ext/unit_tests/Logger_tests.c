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
#include "TextOutputStream.h"
#include "platform.h"
#include "mock_clock.h"
#include "mock_log_custom_sink.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_C_EXT_UNIT_TESTS

static bool hasNonWhiteSpace( StringView strView )
{
    ELASTIC_APM_FOR_EACH_INDEX( i, strView.length )
    {
        if ( ! isWhiteSpace( strView.begin[ i ] ) ) return true;
    }
    return false;
}

static size_t findCharIndexInStringView( char needle, StringView haystack )
{
    ELASTIC_APM_FOR_EACH_INDEX( i, haystack.length )
    {
        if ( haystack.begin[ i ] == needle ) return i;
    }

    return haystack.length;
}

// [Elastic APM PHP Tracer] 2020-05-31 08:33:14.985247+03:00 [PID: 7669][TID: 4321] [DEBUG]    [Util] [util_for_PHP.c:156] [callPhpFunction] Exiting: resultCode: resultSuccess (0)
static
StringView getLogLinePart( size_t partIndex, StringView logLine )
{
    // It's not ELASTIC_APM_STATIC_ARRAY_SIZE( ... ) - 1 to account for the space char after the prefix
    StringView logLineRemainder = stringViewSkipFirstNChars( logLine, ELASTIC_APM_STATIC_ARRAY_SIZE( ELASTIC_APM_LOG_LINE_PREFIX_TRACER_PART ) );
    size_t currentPartIndex = 0;
    bool isInsideDelimitedPart = false;
    while ( true )
    {
        size_t nextDelimiterPos = isInsideDelimitedPart
                ? findCharIndexInStringView( ']', logLineRemainder )
                : findCharIndexInStringView( '[', logLineRemainder );

        if ( ! isInsideDelimitedPart && ! hasNonWhiteSpace( makeStringView( logLineRemainder.begin, nextDelimiterPos ) ) )
        {
            if( nextDelimiterPos >= logLineRemainder.length - 1 ) return makeEmptyStringView();
            isInsideDelimitedPart = ! isInsideDelimitedPart;
            logLineRemainder = stringViewSkipFirstNChars( logLineRemainder, nextDelimiterPos + 1 );
            continue;
        }

        if ( currentPartIndex == partIndex )
            return trimStringView( makeStringView( logLineRemainder.begin, nextDelimiterPos ) );

        if( nextDelimiterPos >= logLineRemainder.length - 1 ) return makeEmptyStringView();

        isInsideDelimitedPart = ! isInsideDelimitedPart;
        ++currentPartIndex;
        logLineRemainder = stringViewSkipFirstNChars( logLineRemainder, nextDelimiterPos + 1 );
    }
}

// [Elastic APM PHP Tracer] 2020-05-31 08:33:14.985247+03:00 [PID: 7669][TID: 4321] [DEBUG]    [Util] [util_for_PHP.c:156] [callPhpFunction] Exiting: resultCode: resultSuccess (0)
//                        ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
static StringView getTimestampPart( StringView logLine )
{
    return getLogLinePart( 0, logLine );
}

// [Elastic APM PHP Tracer] 2020-05-31 08:33:14.985247+03:00 [PID: 7669][TID: 4321] [DEBUG]    [Util] [util_for_PHP.c:156] [callPhpFunction] Exiting: resultCode: resultSuccess (0)
//                                                          ^^^^^^^^^
static StringView getProcessIdPart( StringView logLine )
{
    StringView part = getLogLinePart( 1, logLine );
    StringView pidPrefix = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "PID: " );
    ELASTIC_APM_CMOCKA_ASSERT( isStringViewPrefixIgnoringCase(part, pidPrefix ) );

    return stringViewSkipFirstNChars( part, pidPrefix.length );
}

// [Elastic APM PHP Tracer] 2020-05-31 08:33:14.985247+03:00 [PID: 7669][TID: 4321] [DEBUG]    [Util] [util_for_PHP.c:156] [callPhpFunction] Exiting: resultCode: resultSuccess (0)
//                                                                     ^^^^^^^^^
static StringView getThreadIdPart( StringView logLine )
{
    StringView part = getLogLinePart( 2, logLine );
    StringView tidPrefix = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "TID: " );
    ELASTIC_APM_CMOCKA_ASSERT( isStringViewPrefixIgnoringCase(part, tidPrefix ) );

    return stringViewSkipFirstNChars( part, tidPrefix.length );
}

// [Elastic APM PHP Tracer] 2020-05-31 08:33:14.985247+03:00 [PID: 7669][TID: 4321] [DEBUG]    [Util] [util_for_PHP.c:156] [callPhpFunction] Exiting: resultCode: resultSuccess (0)
//                                                                                ^^^^^
static StringView getLevelPart( StringView logLine )
{
    return getLogLinePart( 3, logLine );
}

// [Elastic APM PHP Tracer] 2020-05-31 08:33:14.985247+03:00 [PID: 7669][TID: 4321] [DEBUG]    [Util] [util_for_PHP.c:156] [callPhpFunction] Exiting: resultCode: resultSuccess (0)
//                                                                                                  ^^^^^^^^^^^^^^^^^^^
static StringView getFileNameLineNumberPart( StringView logLine )
{
    return getLogLinePart( 5, logLine );
}

// [Elastic APM PHP Tracer] 2020-05-31 08:33:14.985247+03:00 [PID: 7669][TID: 4321] [DEBUG]    [Util] [util_for_PHP.c:156] [callPhpFunction] Exiting: resultCode: resultSuccess (0)
//                                                                                                                        ^^^^^^^^^^^^^^^
static StringView getFunctionNamePart( StringView logLine )
{
    return getLogLinePart( 6, logLine );
}

// [Elastic APM PHP Tracer] 2020-05-31 08:33:14.985247+03:00 [PID: 7669][TID: 4321] [DEBUG]    [Util] [util_for_PHP.c:156] [callPhpFunction] Exiting: resultCode: resultSuccess (0)
//                                                                                                                                         ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
static StringView getMessagePart( StringView logLine )
{
    return getLogLinePart( 7, logLine );
}

static
StringView getGlobalMockLogCustomSinkOnlyStatementText()
{
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( numberOfStatementsInMockLogCustomSink( getGlobalMockLogCustomSink() ), 1 );
    const String logStatementTextAsString = getStatementInMockLogCustomSinkContent( getGlobalMockLogCustomSink(), 0 );
    return makeStringViewFromString( logStatementTextAsString );
}

static
void verify_log_output(
        StringView timestamp,
        StringView level,
        size_t logStatementLineNumber,
        StringView funcName,
        StringView msg )
{
    const StringView logStatementText = getGlobalMockLogCustomSinkOnlyStatementText();

    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL( getTimestampPart( logStatementText ), timestamp );

    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL( getLevelPart( logStatementText ), level );

    {
        char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        const char* const ptrBeforeWrite = textOutputStreamGetFreeSpaceBegin( &txtOutStream );
        streamInt( getCurrentProcessId(), &txtOutStream );
        StringView writtenTxt = textOutputStreamViewFrom( &txtOutStream, ptrBeforeWrite );
        ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL( getProcessIdPart( logStatementText ), writtenTxt );
    }

    {
        char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        const char* const ptrBeforeWrite = textOutputStreamGetFreeSpaceBegin( &txtOutStream );
        streamInt( getCurrentProcessId(), &txtOutStream );
        StringView writtenTxt = textOutputStreamViewFrom( &txtOutStream, ptrBeforeWrite );
        ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL( getThreadIdPart( logStatementText ), writtenTxt );
    }

    {
        char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        txtOutStream.autoTermZero = false;
        const char* const ptrBeforeWrite = textOutputStreamGetFreeSpaceBegin( &txtOutStream );
        streamString( extractLastPartOfFilePathString( __FILE__ ), &txtOutStream );
        streamStringView( ELASTIC_APM_STRING_LITERAL_TO_VIEW( ":" ), &txtOutStream );
        streamInt( (int)logStatementLineNumber, &txtOutStream );
        StringView writtenTxt = textOutputStreamViewFrom( &txtOutStream, ptrBeforeWrite );
        ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL( getFileNameLineNumberPart( logStatementText ), writtenTxt );
    }

    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL( getFunctionNamePart( logStatementText ), funcName );

    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL( getMessagePart( logStatementText ), msg );
}

static
void typical_statement( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    setGlobalLoggerLevelForCustomSink( logLevel_trace );

    setMockCurrentTime(
            /* years: */ 2123,
            /* months: */ 7,
            /* days: */ 28,
            /* hours: */ 14,
            /* minutes: */ 37,
            /* seconds: */ 19,
            /* microseconds: */ 987654,
            /* secondsAheadUtc: */ -( 11 * 60 * 60 + 23 * 60 + 30 ) );

    clearMockLogCustomSink( getGlobalMockLogCustomSink() );
    const size_t logStatementLineNumber = __LINE__; ELASTIC_APM_LOG_TRACE( "Message and some context: %d, %s", 122333, "444455555" );

    verify_log_output(
            ELASTIC_APM_STRING_LITERAL_TO_VIEW( "2123-07-28 14:37:19.987654-11:24" ),
            makeStringViewFromString( logLevelToName( logLevel_trace ) ),
            logStatementLineNumber,
            ELASTIC_APM_STRING_LITERAL_TO_VIEW( __FUNCTION__ ),
            ELASTIC_APM_STRING_LITERAL_TO_VIEW( "Message and some context: 122333, 444455555" ) );
}

static
void empty_message( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    setGlobalLoggerLevelForCustomSink( logLevel_debug );

    setMockCurrentTime(
            /* years: */ 2019,
            /* months: */ 11,
            /* days: */ 17,
            /* hours: */ 07,
            /* minutes: */ 23,
            /* seconds: */ 60,
            /* microseconds: */ 999999,
            /* secondsAheadUtc: */ ( 25 * 60 * 60 + 51 * 60 + 29 ) );

    clearMockLogCustomSink( getGlobalMockLogCustomSink() );
    const size_t logStatementLineNumber = __LINE__; ELASTIC_APM_LOG_DEBUG( "%s", "" );

    verify_log_output(
            ELASTIC_APM_STRING_LITERAL_TO_VIEW( "2019-11-17 07:23:60.999999+25:51" ),
            makeStringViewFromString( logLevelToName( logLevel_debug ) ),
            logStatementLineNumber,
            ELASTIC_APM_STRING_LITERAL_TO_VIEW( __FUNCTION__ ),
            ELASTIC_APM_STRING_LITERAL_TO_VIEW( "" ) );
}

static
void statements_filtered_according_to_current_level_helper(
        LogLevel currentLevel,
        LogLevel statementLevel,
        String expectedMsg )
{
    if ( statementLevel > currentLevel )
    {
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( numberOfStatementsInMockLogCustomSink( getGlobalMockLogCustomSink() ), 0 );
        return;
    }

    const StringView logStatementText = getGlobalMockLogCustomSinkOnlyStatementText();

    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL(
            getMessagePart( logStatementText ),
            makeStringViewFromString( expectedMsg ) );

    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL(
            getLevelPart( logStatementText ),
            makeStringViewFromString( logLevelToName( statementLevel ) ) );

    clearMockLogCustomSink( getGlobalMockLogCustomSink() );
}

static
void statements_filtered_according_to_current_level( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    const char* msg = "Dummy message";

    ELASTIC_APM_FOR_EACH_INDEX( currentLevelIndex, numberOfLogLevels )
    {
        LogLevel currentLevel = logLevel_off + currentLevelIndex;

        setGlobalLoggerLevelForCustomSink( currentLevel );
        clearMockLogCustomSink( getGlobalMockLogCustomSink() );

        ELASTIC_APM_LOG_CRITICAL( "%s", msg );
        statements_filtered_according_to_current_level_helper( currentLevel, logLevel_critical, msg );
        ELASTIC_APM_LOG_ERROR( "%s", msg );
        statements_filtered_according_to_current_level_helper( currentLevel, logLevel_error, msg );
        ELASTIC_APM_LOG_WARNING( "%s", msg );
        statements_filtered_according_to_current_level_helper( currentLevel, logLevel_warning, msg );
        ELASTIC_APM_LOG_INFO( "%s", msg );
        statements_filtered_according_to_current_level_helper( currentLevel, logLevel_info, msg );
        ELASTIC_APM_LOG_DEBUG( "%s", msg );
        statements_filtered_according_to_current_level_helper( currentLevel, logLevel_debug, msg );
        ELASTIC_APM_LOG_TRACE( "%s", msg );
        statements_filtered_according_to_current_level_helper( currentLevel, logLevel_trace, msg );

        ELASTIC_APM_FOR_EACH_INDEX( statementLevelDiff, numberOfLogLevels - 1 )
        {
            const LogLevel statementLevel = logLevel_off + 1 + statementLevelDiff;
            ELASTIC_APM_LOG_WITH_LEVEL( statementLevel, "%s", msg );
            statements_filtered_according_to_current_level_helper( currentLevel, statementLevel, msg );
        }
    }
}

int run_Logger_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( typical_statement ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( empty_message ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( statements_filtered_according_to_current_level ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
