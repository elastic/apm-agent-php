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

#include "unit_test_util.h"
#include "TextOutputStream.h"
#include "platform.h"
#include "mock_clock.h"
#include "mock_log_custom_sink.h"

static inline bool isWhiteSpace( char c )
{
    return c == ' ' || c == '\t' || c == '\n' || c == '\r';
}

static inline StringView trimStringView( StringView src )
{
    size_t beginIndex = 0;
    size_t endIndex = src.length;
    for ( ; beginIndex < src.length && ( isWhiteSpace( src.begin[ beginIndex ] ) ) ; ++beginIndex );
    for ( ; endIndex > beginIndex && ( isWhiteSpace( src.begin[ endIndex - 1 ] ) ) ; --endIndex );

    return makeStringView( src.begin + beginIndex, endIndex - beginIndex );
}

static void trim_StringView_test( void** testFixtureState )
{
    ELASTICAPM_UNUSED( testFixtureState );

    ELASTICAPM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL( trimStringView( ELASTICAPM_STRING_LITERAL_TO_VIEW( "ABC" ) ), "ABC" );
    ELASTICAPM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL( trimStringView( ELASTICAPM_STRING_LITERAL_TO_VIEW( " ABC" ) ), "ABC" );
    ELASTICAPM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL( trimStringView( ELASTICAPM_STRING_LITERAL_TO_VIEW( "ABC\t" ) ), "ABC" );
    ELASTICAPM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL( trimStringView( ELASTICAPM_STRING_LITERAL_TO_VIEW( " AB\tC\r\n" ) ), "AB\tC" );
    ELASTICAPM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL( trimStringView( ELASTICAPM_STRING_LITERAL_TO_VIEW( "" ) ), "" );
    ELASTICAPM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL( trimStringView( ELASTICAPM_STRING_LITERAL_TO_VIEW( " " ) ), "" );
    ELASTICAPM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL( trimStringView( ELASTICAPM_STRING_LITERAL_TO_VIEW( " \n\r\t" ) ), "" );
}

// 2020-03-08 19:37:30.683211+02:00 | TRACE    | 10484 | lifecycle.c:406      | elasticApmRequestInit          | Message
static inline size_t findCharIndexInStringView( char needle, StringView haystack )
{
    ELASTICAPM_FOR_EACH_INDEX( i, haystack.length )
    {
        if ( haystack.begin[ i ] == needle ) return i;
    }

    return haystack.length;
}

// 2020-03-08 19:37:30.683211+02:00 | TRACE    | 10484 | lifecycle.c:406      | elasticApmRequestInit          | Message
static inline StringView getLogLinePart( size_t partIndex, StringView logLine )
{
    StringView logLineRemainder = logLine;
    size_t currentPartIndex = 0;
    while ( true )
    {
        size_t nextPartSeparatorIndex = findCharIndexInStringView( '|', logLineRemainder );
        if ( currentPartIndex == partIndex )
            return trimStringView( makeStringView( logLineRemainder.begin, nextPartSeparatorIndex ) );

        ELASTICAPM_CMOCKA_ASSERT( nextPartSeparatorIndex < logLineRemainder.length );

        logLineRemainder = stringViewSkipFirstNChars( logLineRemainder, nextPartSeparatorIndex + 1 );
        ++currentPartIndex;
    }
}

// 2020-03-08 19:37:30.683211+02:00 | TRACE    | 10484 | lifecycle.c:406      | elasticApmRequestInit          | Message
// ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
static inline StringView getTimestampPart( StringView logLine )
{
    return getLogLinePart( 0, logLine );
}

// 2020-03-08 19:37:30.683211+02:00 | TRACE    | 10484 | lifecycle.c:406      | elasticApmRequestInit          | Message
//                                    ^^^^^
static inline StringView getLevelPart( StringView logLine )
{
    return getLogLinePart( 1, logLine );
}

// 2020-03-08 19:37:30.683211+02:00 | TRACE    | 10484:12345 | lifecycle.c:406      | elasticApmRequestInit          | Message
//                                               ^^^^^^^^^^^
static inline StringView getProcessThreadIdsPart( StringView logLine )
{
    return getLogLinePart( 2, logLine );
}

// 2020-03-08 19:37:30.683211+02:00 | TRACE    | 10484 | lifecycle.c:406      | elasticApmRequestInit          | Message
//                                                       ^^^^^^^^^^^^^^^
static inline StringView getFileNameLineNumberPart( StringView logLine )
{
    return getLogLinePart( 3, logLine );
}

// 2020-03-08 19:37:30.683211+02:00 | TRACE    | 10484 | lifecycle.c:406      | elasticApmRequestInit          | Message
//                                                                              ^^^^^^^^^^^^^^^^^^^^^
static inline StringView getFunctionNamePart( StringView logLine )
{
    return getLogLinePart( 4, logLine );
}

// 2020-03-08 19:37:30.683211+02:00 | TRACE    | 10484 | lifecycle.c:406      | elasticApmRequestInit          | Message
//                                                                                                               ^^^^^^^
static inline StringView getMessagePart( StringView logLine )
{
    return getLogLinePart( 5, logLine );
}

static
StringView getGlobalMockLogCustomSinkOnlyStatementText()
{
    ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( numberOfStatementsInMockLogCustomSink( getGlobalMockLogCustomSink() ), 1 );
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

    ELASTICAPM_CMOCKA_ASSERT_STRING_VIEW_EQUAL( getTimestampPart( logStatementText ), timestamp );

    ELASTICAPM_CMOCKA_ASSERT_STRING_VIEW_EQUAL( getLevelPart( logStatementText ), level );

    {
        char txtOutStreamBuf[ ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        TextOutputStream txtOutStream = ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        const char* const ptrBeforeWrite = textOutputStreamGetFreeSpaceBegin( &txtOutStream );
        streamInt( getCurrentProcessId(), &txtOutStream );
        StringView writtenTxt = textOutputStreamViewFrom( &txtOutStream, ptrBeforeWrite );
        ELASTICAPM_CMOCKA_ASSERT_STRING_VIEW_EQUAL( getProcessThreadIdsPart( logStatementText ), writtenTxt );
    }

    {
        char txtOutStreamBuf[ ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        TextOutputStream txtOutStream = ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        txtOutStream.autoTermZero = false;
        const char* const ptrBeforeWrite = textOutputStreamGetFreeSpaceBegin( &txtOutStream );
        streamString( extractLastPartOfFilePathString( __FILE__ ), &txtOutStream );
        streamStringView( ELASTICAPM_STRING_LITERAL_TO_VIEW( ":" ), &txtOutStream );
        streamInt( (int)logStatementLineNumber, &txtOutStream );
        StringView writtenTxt = textOutputStreamViewFrom( &txtOutStream, ptrBeforeWrite );
        ELASTICAPM_CMOCKA_ASSERT_STRING_VIEW_EQUAL( getFileNameLineNumberPart( logStatementText ), writtenTxt );
    }

    ELASTICAPM_CMOCKA_ASSERT_STRING_VIEW_EQUAL( getFunctionNamePart( logStatementText ), funcName );

    ELASTICAPM_CMOCKA_ASSERT_STRING_VIEW_EQUAL( getMessagePart( logStatementText ), msg );
}

static
void typical_statement( void** testFixtureState )
{
    ELASTICAPM_UNUSED( testFixtureState );

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
    const size_t logStatementLineNumber = __LINE__; ELASTICAPM_LOG_TRACE( "Message and some context: %d, %s", 122333, "444455555" );

    verify_log_output(
            ELASTICAPM_STRING_LITERAL_TO_VIEW( "2123-07-28 14:37:19.987654-11:24" ),
            makeStringViewFromString( logLevelNames[ logLevel_trace ] ),
            logStatementLineNumber,
            ELASTICAPM_STRING_LITERAL_TO_VIEW( __FUNCTION__ ),
            ELASTICAPM_STRING_LITERAL_TO_VIEW( "Message and some context: 122333, 444455555" ) );
}

static
void empty_message( void** testFixtureState )
{
    ELASTICAPM_UNUSED( testFixtureState );

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
    const size_t logStatementLineNumber = __LINE__; ELASTICAPM_LOG_DEBUG( "%s", "" );

    verify_log_output(
            ELASTICAPM_STRING_LITERAL_TO_VIEW( "2019-11-17 07:23:60.999999+25:51" ),
            makeStringViewFromString( logLevelNames[ logLevel_debug ] ),
            logStatementLineNumber,
            ELASTICAPM_STRING_LITERAL_TO_VIEW( __FUNCTION__ ),
            ELASTICAPM_STRING_LITERAL_TO_VIEW( "" ) );
}

static
void statements_filtered_according_to_current_level_helper(
        LogLevel currentLevel,
        LogLevel statementLevel,
        String expectedMsg )
{
    if ( statementLevel > currentLevel )
    {
        ELASTICAPM_CMOCKA_ASSERT_INT_EQUAL( numberOfStatementsInMockLogCustomSink( getGlobalMockLogCustomSink() ), 0 );
        return;
    }

    const StringView logStatementText = getGlobalMockLogCustomSinkOnlyStatementText();

    ELASTICAPM_CMOCKA_ASSERT_STRING_VIEW_EQUAL(
            getMessagePart( logStatementText ),
            makeStringViewFromString( expectedMsg ) );

    ELASTICAPM_CMOCKA_ASSERT_STRING_VIEW_EQUAL(
            getLevelPart( logStatementText ),
            makeStringViewFromString( logLevelNames[ statementLevel ] ) );

    clearMockLogCustomSink( getGlobalMockLogCustomSink() );
}

static
void statements_filtered_according_to_current_level( void** testFixtureState )
{
    ELASTICAPM_UNUSED( testFixtureState );

    const char* msg = "Dummy message";

    ELASTICAPM_FOR_EACH_INDEX( currentLevelIndex, numberOfLogLevels )
    {
        LogLevel currentLevel = logLevel_off + currentLevelIndex;

        setGlobalLoggerLevelForCustomSink( currentLevel );
        clearMockLogCustomSink( getGlobalMockLogCustomSink() );

        ELASTICAPM_LOG_CRITICAL( "%s", msg );
        statements_filtered_according_to_current_level_helper( currentLevel, logLevel_critical, msg );
        ELASTICAPM_LOG_ERROR( "%s", msg );
        statements_filtered_according_to_current_level_helper( currentLevel, logLevel_error, msg );
        ELASTICAPM_LOG_WARNING( "%s", msg );
        statements_filtered_according_to_current_level_helper( currentLevel, logLevel_warning, msg );
        ELASTICAPM_LOG_NOTICE( "%s", msg );
        statements_filtered_according_to_current_level_helper( currentLevel, logLevel_notice, msg );
        ELASTICAPM_LOG_INFO( "%s", msg );
        statements_filtered_according_to_current_level_helper( currentLevel, logLevel_info, msg );
        ELASTICAPM_LOG_DEBUG( "%s", msg );
        statements_filtered_according_to_current_level_helper( currentLevel, logLevel_debug, msg );
        ELASTICAPM_LOG_TRACE( "%s", msg );
        statements_filtered_according_to_current_level_helper( currentLevel, logLevel_trace, msg );

        ELASTICAPM_FOR_EACH_INDEX( statementLevelDiff, numberOfLogLevels - 1 )
        {
            const LogLevel statementLevel = logLevel_off + 1 + statementLevelDiff;
            ELASTICAPM_LOG_WITH_LEVEL( statementLevel, "%s", msg );
            statements_filtered_according_to_current_level_helper( currentLevel, statementLevel, msg );
        }
    }
}

int run_Logger_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTICAPM_CMOCKA_UNIT_TEST( trim_StringView_test ),
        ELASTICAPM_CMOCKA_UNIT_TEST( typical_statement ),
        ELASTICAPM_CMOCKA_UNIT_TEST( empty_message ),
        ELASTICAPM_CMOCKA_UNIT_TEST( statements_filtered_according_to_current_level ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
