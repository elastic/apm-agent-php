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

#include "log.h"
#include <stdio.h>
#include <stdarg.h>
#include <math.h>
#ifndef PHP_WIN32
#   include <syslog.h>
#endif
#include "elasticapm_clock.h"
#include "util.h"
#include "elasticapm_alloc.h"
#include "platform.h"
#include "TextOutputStream.h"
#include "Tracer.h"

#define ELASTICAPM_CURRENT_LOG_CATEGORY ELASTICAPM_LOG_CATEGORY_LOG

String logLevelNames[numberOfLogLevels] =
        {
                [logLevel_off] = "OFF", [logLevel_critical] = "CRITICAL", [logLevel_error] = "ERROR", [logLevel_warning] = "WARNING", [logLevel_notice] = "NOTICE", [logLevel_info] = "INFO", [logLevel_debug] = "DEBUG", [logLevel_trace] = "TRACE"
        };

enum
{
    loggerMessageBufferSize = 1000 * 1000 + 1
};

struct TimeZoneShift
{
    bool isPositive;
    UInt8 hours;
    UInt8 minutes;
};
typedef struct TimeZoneShift TimeZoneShift;

struct LocalTime
{
    UInt16 years;
    UInt8 months;
    UInt8 days;
    UInt8 hours;
    UInt8 minutes;
    UInt8 seconds;
    UInt32 microseconds;
    TimeZoneShift timeZoneShift;
};
typedef struct LocalTime LocalTime;

static void calcTimeZoneShift( long secondsAheadUtc, TimeZoneShift* timeZoneShift )
{
    const long secondsAheadUtcAbs = secondsAheadUtc >= 0 ? secondsAheadUtc : - secondsAheadUtc;
    const unsigned long minutesAheadUtcAbs = (long) ( round( secondsAheadUtcAbs / 60.0 ) );

    timeZoneShift->isPositive = secondsAheadUtc >= 0;
    timeZoneShift->minutes = (UInt8) ( minutesAheadUtcAbs % 60 );
    timeZoneShift->hours = (UInt8) ( minutesAheadUtcAbs / 60 );
}

static void getCurrentLocalTime( LocalTime* localCurrentTime )
{
    struct timeval currentTime_UTC_timeval = { 0 };
    struct tm currentTime_local_tm = { 0 };
    long secondsAheadUtc = 0;

    if ( getSystemClockCurrentTimeAsUtc( &currentTime_UTC_timeval ) != 0 ) return;
    if ( ! convertUtcToLocalTime( currentTime_UTC_timeval.tv_sec, &currentTime_local_tm, &secondsAheadUtc ) ) return;

    // tm_year is years since 1900
    localCurrentTime->years = (UInt16) ( 1900 + currentTime_local_tm.tm_year );
    // tm_mon is months since January - [0, 11]
    localCurrentTime->months = (UInt8) ( currentTime_local_tm.tm_mon + 1 );
    localCurrentTime->days = (UInt8) currentTime_local_tm.tm_mday;
    localCurrentTime->hours = (UInt8) currentTime_local_tm.tm_hour;
    localCurrentTime->minutes = (UInt8) currentTime_local_tm.tm_min;
    localCurrentTime->seconds = (UInt8) currentTime_local_tm.tm_sec;
    localCurrentTime->microseconds = (UInt32) currentTime_UTC_timeval.tv_usec;

    calcTimeZoneShift( secondsAheadUtc, &( localCurrentTime->timeZoneShift ) );
}

// 2020-02-15 21:51:32.123456+02:00 [ERROR]    [Ext-Infra]     [lifecycle.c:482] [sendEventsToApmServer] Couldn't connect to server blah blah blah blah blah blah blah blah | PID: 12345 | TID: 67890
// 2020-02-15 21:51:32.123456+02:00 [WARNING]  [Configuration] [ConfigManager.c:45] [constructSnapshotUsingDefaults] Not found blah blah blah blah blah blah blah blah | PID: 12345 | TID: 67890
// 2020-02-15 21:51:32.123456+02:00 [CRITICAL] [PHP-Bootstrap] [BootstrapShutdownHelper.php:123] [constructSnapshotUsingDefaults] Send failed. Error message: Couldn't connect to server. server_url: `http://localhost:8200' | PID: 123 | TID: 345
// ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^ ^^^^^^^^^^ ^^^^^^^^^^^^^^^ ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^ ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^ ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^   ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
// ^                                ^          ^               ^                                 ^                                ^
// ^                                ^          ^               ^                                 ^                                Message text
// ^                                ^          ^               ^                                 function name
// ^                                ^          ^               file name:line number
// ^                                ^          ^     category
// ^                                level (wrapped in [] and padded with spaces on the right to 10 chars)
// timestamp (no padding)

// 2020-02-15 21:51:32.123456+02:00 | ERROR    | 12345:67890 | lifecycle.c:482                     | sendEventsToApmServer          | Couldn't connect to server blah blah blah blah blah blah blah blah
// 2020-02-15 21:51:32.123456+02:00 | WARNING  | 12345:67890 | ConfigManager.c:45                  | constructSnapshotUsingDefaults | Not found blah blah blah blah blah blah blah blah
// 2020-02-15 21:51:32.123456+02:00 | CRITICAL |   345:  345 | BootstrapShutdownHelper.php:123     | constructSnapshotUsingDefaults | Send failed. Error message: Couldn't connect to server. server_url: `http://localhost:8200'
// ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^   ^^^^^^^^   ^^^^^ ^^^^^   ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^   ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^   ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
// ^                                  ^          ^     ^       ^                                     ^                                ^
// ^                                  ^          ^     ^       ^                                     ^                                Message text
// ^                                  ^          ^     ^       ^                                     function name (padded with spaces on the right to 30 chars)
// ^                                  ^          ^     ^       file name:line number (padded with spaces on the right to 35 chars)
// ^                                  ^          ^     thread ID (padded with spaces on the left to 5 chars) - included only if ZTS is defined
// ^                                  ^          process ID (padded with spaces on the left to 5 chars)
// ^                                  level (padded with spaces on the right to 8 chars)
// timestamp (no padding)


static void appendTimestamp( TextOutputStream* txtOutStream )
{
    // 2020-02-15 21:51:32.123456+02:00

    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    LocalTime timestamp = { 0 };
    getCurrentLocalTime( &timestamp );

    streamPrintf(
            txtOutStream
            , "%04d-%02d-%02d %02d:%02d:%02d.%06d%c%02d:%02d"
            , timestamp.years
            , timestamp.months
            , timestamp.days
            , timestamp.hours
            , timestamp.minutes
            , timestamp.seconds
            , timestamp.microseconds
            , timestamp.timeZoneShift.isPositive ? '+' : '-'
            , timestamp.timeZoneShift.hours
            , timestamp.timeZoneShift.minutes );
}

static const char logLinePartsSeparator[] = " ";

static void appendSeparator( TextOutputStream* txtOutStream )
{
    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    streamStringView( ELASTICAPM_STRING_LITERAL_TO_VIEW( logLinePartsSeparator ), txtOutStream );
}

#define ELASTICAPM_LOG_FMT_PAD_START ""

#define ELASTICAPM_LOG_FMT_NUMBER_PAD_START( minWidth ) \
    "%" ELASTICAPM_LOG_FMT_PAD_START ELASTICAPM_PP_STRINGIZE( minWidth ) "d"

static
void streamPadding( Int64 paddingLength, TextOutputStream* txtOutStream )
{
    if ( paddingLength <= 0 ) return;
    ELASTICAPM_REPEAT_N_TIMES( ((size_t)paddingLength) ) streamChar( ' ', txtOutStream );
}

static
void appendLevel( LogLevel level, TextOutputStream* txtOutStream )
{
    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    const char* posBeforeWrite = textOutputStreamGetFreeSpaceBegin( txtOutStream );
    streamChar( '[', txtOutStream );
    if ( level < numberOfLogLevels )
    {
        // if it's a level with a name
        streamPrintf( txtOutStream, "%s", logLevelNames[ level ] );
    }
    else
    {
        // otherwise print it as a number
        // TODO: Sergey Kleyman: Test with log level 100
        streamPrintf( txtOutStream, "%d", level );
    }
    streamChar( ']', txtOutStream );
    const char* posBeforeAfter = textOutputStreamGetFreeSpaceBegin( txtOutStream );
    const int minWidth = 10;
    streamPadding( minWidth - ( posBeforeAfter - posBeforeWrite ), txtOutStream );
}

static
void appendCategory( StringView category, TextOutputStream* txtOutStream )
{
    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    streamChar( '[', txtOutStream );
    streamStringView( category, txtOutStream );
    streamChar( ']', txtOutStream );
}

#ifndef ELASTICAPM_HAS_THREADS_01
#   ifdef ZTS
#       define ELASTICAPM_HAS_THREADS_01 1
#   else
#       define ELASTICAPM_HAS_THREADS_01 0
#   endif
#endif

static void appendProcessThreadIds( TextOutputStream* txtOutStream )
{
    // [PID: 12345] [TID: 67890]

    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    pid_t processId = getCurrentProcessId();

    streamStringView( ELASTICAPM_STRING_LITERAL_TO_VIEW("[PID: "), txtOutStream );
    streamPrintf( txtOutStream, "%u", processId );
    streamChar( ']', txtOutStream );

#   if ( ELASTICAPM_HAS_THREADS_01 != 0 )

    appendSeparator( txtOutStream );

    pid_t threadId = getCurrentThreadId();

    streamStringView( ELASTICAPM_STRING_LITERAL_TO_VIEW("[TID: "), txtOutStream );
    streamPrintf( txtOutStream, "%u", threadId );
    streamChar( ']', txtOutStream );

#   endif
}

static
void appendFileNameLineNumberPart( StringView filePath, UInt lineNumber, TextOutputStream* txtOutStream )
{
    // lifecycle.c:482

    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    streamChar( '[', txtOutStream );
    streamStringView( extractLastPartOfFilePathStringView( filePath ), txtOutStream );
    streamChar( ':', txtOutStream );
    streamPrintf( txtOutStream, "%u", lineNumber );
    streamChar( ']', txtOutStream );
}

static void appendFunctionName( StringView funcName, TextOutputStream* txtOutStream )
{
    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    streamChar( '[', txtOutStream );
    streamStringView( funcName, txtOutStream );
    streamChar( ']', txtOutStream );
}

static
StringView buildCommonPrefix(
        LogLevel statementLevel
        , StringView category
        , StringView filePath
        , UInt lineNumber
        , StringView funcName
        , char* buffer
        , size_t bufferSize
)
{
    // 2020-05-08 08:18:54.154244+02:00 [DEBUG]    [Configuration] [ConfigManager.c:1127] [ensureConfigManagerHasLatestConfig] Current configuration is already the latest [TransactionId: xyz] [namespace: Impl\AutoInstrument]
    // ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

    TextOutputStream txtOutStream = makeTextOutputStream( buffer, bufferSize );
    // We don't need terminating '\0' after the prefix because we return it as StringView
    txtOutStream.autoTermZero = false;
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( &txtOutStream, &txtOutStreamStateOnEntryStart ) )
    {
        return ELASTICAPM_STRING_LITERAL_TO_VIEW( ELASTICAPM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER );
    }

    appendTimestamp( &txtOutStream );
    appendSeparator( &txtOutStream );
    appendLevel( statementLevel, &txtOutStream );
    appendSeparator( &txtOutStream );
    appendCategory( category, &txtOutStream );
    appendSeparator( &txtOutStream );
    appendFileNameLineNumberPart( filePath, lineNumber, &txtOutStream );
    appendSeparator( &txtOutStream );
    appendFunctionName( funcName, &txtOutStream );
    appendSeparator( &txtOutStream );

    textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, &txtOutStream );
    return textOutputStreamContentAsStringView( &txtOutStream );
}

static
StringView findEndOfLineSequence( StringView text )
{
    // The order in endOfLineSequences is important because we need to check longer sequences first
    StringView endOfLineSequences[] =
            {
                    ELASTICAPM_STRING_LITERAL_TO_VIEW( "\r\n" )
                    , ELASTICAPM_STRING_LITERAL_TO_VIEW( "\n" )
                    , ELASTICAPM_STRING_LITERAL_TO_VIEW( "\r" )
            };

    ELASTICAPM_FOR_EACH_INDEX( textPos, text.length )
    {
        ELASTICAPM_FOR_EACH_INDEX( eolSeqIndex, ELASTICAPM_STATIC_ARRAY_SIZE( endOfLineSequences ) )
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

static
StringView insertPrefixAtEachNewLine(
        Logger* logger
        , StringView sinkSpecificPrefix
        , StringView commonPrefix
        , StringView oldMessage
        , size_t maxSizeForNewMessage
)
{
    ELASTICAPM_ASSERT_VALID_PTR( logger->auxMessageBuffer );
    ELASTICAPM_ASSERT_LE_UINT64( maxSizeForNewMessage, loggerMessageBufferSize );

    TextOutputStream txtOutStream = makeTextOutputStream( logger->auxMessageBuffer, maxSizeForNewMessage );
    // We don't need terminating '\0' after the prefix because we return it as StringView
    txtOutStream.autoTermZero = false;
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( &txtOutStream, &txtOutStreamStateOnEntryStart ) )
    {
        return ELASTICAPM_STRING_LITERAL_TO_VIEW( ELASTICAPM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER );
    }

    const char* oldMessageEnd = stringViewEnd( oldMessage );
    StringView oldMessageLeft = oldMessage;
    for ( ;; )
    {
        StringView eolSeq = findEndOfLineSequence( oldMessageLeft );
        if ( isEmptyStringView( eolSeq ) ) break;

        streamStringView( makeStringViewFromBeginEnd( oldMessageLeft.begin, stringViewEnd( eolSeq ) ), &txtOutStream );
        streamStringView( sinkSpecificPrefix, &txtOutStream );
        streamStringView( commonPrefix, &txtOutStream );
        streamIndent( /* nestingDepth */ 1, &txtOutStream );
        oldMessageLeft = makeStringViewFromBeginEnd( stringViewEnd( eolSeq ), oldMessageEnd );
    }

    // If we didn't write anything to new message part then it means the old one is just one line
    // so there's no need to insert any prefixes
    if ( isEmptyStringView( textOutputStreamContentAsStringView( &txtOutStream ) ) ) return makeEmptyStringView();

    streamStringView( oldMessageLeft, &txtOutStream );

    textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, &txtOutStream );
    return textOutputStreamContentAsStringView( &txtOutStream );
}

static
String concatPrefixAndMsg(
        Logger* logger
        , StringView sinkSpecificPrefix
        , StringView sinkSpecificEndOfLine
        , StringView commonPrefix
        , bool prefixNewLines
        , String msgFmt
        , va_list msgArgs
)
{
    ELASTICAPM_ASSERT_VALID_PTR( logger );
    ELASTICAPM_ASSERT_VALID_PTR( logger->messageBuffer );

    TextOutputStream txtOutStream = makeTextOutputStream( logger->messageBuffer, loggerMessageBufferSize );
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( &txtOutStream, &txtOutStreamStateOnEntryStart ) )
    {
        return ELASTICAPM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;
    }

    streamStringView( sinkSpecificPrefix, &txtOutStream );
    streamStringView( commonPrefix, &txtOutStream );
    const char* messagePartBegin = textOutputStreamGetFreeSpaceBegin( &txtOutStream );
    streamVPrintf( &txtOutStream, msgFmt, msgArgs );
    if ( prefixNewLines )
    {
        StringView messagePart = textOutputStreamViewFrom( &txtOutStream, messagePartBegin );
        size_t maxSizeForNewMessage = textOutputStreamGetFreeSpaceSize( &txtOutStream ) + messagePart.length - sinkSpecificEndOfLine.length;
        StringView newMessagePart = insertPrefixAtEachNewLine( logger, sinkSpecificPrefix, commonPrefix, messagePart, maxSizeForNewMessage );
        if ( ! isEmptyStringView( newMessagePart ) )
        {
            textOutputStreamGoBack( &txtOutStream, messagePart.length );
            streamStringView( newMessagePart, &txtOutStream );
        }
    }
    streamStringView( sinkSpecificEndOfLine, &txtOutStream );
    return textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, &txtOutStream );
}

static
void writeToStderr( Logger* logger, LogLevel statementLevel, StringView commonPrefix, String msgFmt, va_list msgArgs )
{
    String fullText = concatPrefixAndMsg(
            logger
            , /* sinkSpecificPrefix: */ ELASTICAPM_STRING_LITERAL_TO_VIEW( "" )
            , /* sinkSpecificEndOfLine: */ ELASTICAPM_STRING_LITERAL_TO_VIEW( "\n" )
            , commonPrefix
            , /* prefixNewLines: */ true
            , msgFmt
            , msgArgs );

    fprintf( stderr, "%s", fullText );

    if ( statementLevel <= logLevel_info ) fflush( stderr );
}

static
StringView buildPrefixForSinkMixedWithOtherProcesses( char* buffer, size_t bufferSize )
{
    TextOutputStream txtOutStream = makeTextOutputStream( buffer, bufferSize );
    txtOutStream.autoTermZero = false;
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( &txtOutStream, &txtOutStreamStateOnEntryStart ) )
        return ELASTICAPM_STRING_LITERAL_TO_VIEW( ELASTICAPM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER );

    streamStringView(ELASTICAPM_STRING_LITERAL_TO_VIEW( "Elastic APM PHP Tracer " ), &txtOutStream);
    appendProcessThreadIds( &txtOutStream );
    streamStringView(ELASTICAPM_STRING_LITERAL_TO_VIEW( " " ), &txtOutStream);

    textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, &txtOutStream );
    return textOutputStreamContentAsStringView( &txtOutStream );
}

#ifndef PHP_WIN32 // syslog is not supported on Windows
//////////////////////////////////////////////////////////////////////////////
//
// syslog
//
static
int logLevelToSyslog( LogLevel level )
{
    switch ( level )
    {
        case logLevel_trace: // NOLINT(bugprone-branch-clone)
        case logLevel_debug:
            return LOG_DEBUG;

        case logLevel_info:
            return LOG_INFO;

        case logLevel_notice:
            return LOG_NOTICE;

        case logLevel_warning:
            return LOG_WARNING;

        case logLevel_error:
            return LOG_ERR;

        case logLevel_critical:
            return LOG_CRIT;

        default:
            return LOG_DEBUG;
    }
}

static
void writeToSyslog( Logger* logger, LogLevel level, StringView commonPrefix, String msgFmt, va_list msgArgs )
{
    char sinkSpecificPrefixBuffer[ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];

    String fullText = concatPrefixAndMsg(
            logger
            , /* sinkSpecificPrefix: */ buildPrefixForSinkMixedWithOtherProcesses( sinkSpecificPrefixBuffer, ELASTICAPM_STATIC_ARRAY_SIZE( sinkSpecificPrefixBuffer ) )
            , /* sinkSpecificEndOfLine: */ ELASTICAPM_STRING_LITERAL_TO_VIEW( "" )
            , commonPrefix
            , /* prefixNewLines: */ false
            , msgFmt
            , msgArgs );

    syslog( logLevelToSyslog( level ), "%s", fullText );
}
//
// syslog
//
//////////////////////////////////////////////////////////////////////////////
#endif // #ifndef PHP_WIN32 // syslog is not supported on Windows

#ifdef PHP_WIN32
static
void writeToWinSysDebug( Logger* logger, StringView commonPrefix, String msgFmt, va_list msgArgs )
{
    char sinkSpecificPrefixBuffer[ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];

    String fullText = concatPrefixAndMsg(
            logger
            , /* sinkSpecificPrefix: */ buildPrefixForSinkMixedWithOtherProcesses( sinkSpecificPrefixBuffer, ELASTICAPM_STATIC_ARRAY_SIZE( sinkSpecificPrefixBuffer ) )
            , /* sinkSpecificEndOfLine: */ ELASTICAPM_STRING_LITERAL_TO_VIEW( "\n" )
            , commonPrefix
            , /* prefixNewLines: */ true
            , msgFmt
            , msgArgs );

    writeToWindowsSystemDebugger( fullText );
}

#endif

// TODO: Sergey Kleyman: Implement log to file
//static bool openAndAppendToFile( Logger* logger, StringView fullText )
//{
//#ifdef PHP_WIN32
//
//    FILE* file = fopen( logger->config.file, "a" );
//
//    fwrite(  );
//
//#else
//
//    // Use lower level system calls - "open" and "write" to get stronger guarantee:
//    //
//    //      O_APPEND
//    //              The file is opened in append mode.  Before each write(2), the
//    //              file offset is positioned at the end of the file, as if with
//    //              lseek(2).  The modification of the file offset and the write
//    //              operation are performed as a single atomic step.
//    //
//    // http://man7.org/linux/man-pages/man2/open.2.html
//    //
//    int file = open( logger->config.file, O_WRONLY | O_APPEND | O_CREAT );
//
//
//
//#endif
//
//    #ifdef PHP_WIN32
//    #else
//    write( , , fullLine );
//    #endif
//
//    if ( file )
//    {
//
//    }
//
//    finally:
//    if ( file != NULL )
//    {
//        #ifdef PHP_WIN32
//        fclose( file );
//        #else
//        close( file );
//        #endif
//        file = NULL;
//    }
//
//    failure:
//    logger->fileFailed = true;
//    goto finally;
//}
//
//static void writeToFile( Logger* logger, StringView prefix, String msgFmt, va_list msgArgs )
//{
//    if ( isNullOrEmtpyString( logger->config.file ) ) return;
//    if ( logger->fileFailed ) return;
//
//    openAndAppendToFile(
//            logger
//            , concatPrefixAndMsg( logger
//                                  , /* sinkPrefix: */ ELASTICAPM_STRING_LITERAL_TO_VIEW( "" )
//                                  , /* end-of-line: */ ELASTICAPM_STRING_LITERAL_TO_VIEW( "" )
//                                  , prefix
//                                  , msgFmt
//                                  , msgArgs ) );
//}

#ifdef ELASTICAPM_LOG_CUSTOM_SINK_FUNC

// Declare to avoid warnings
void ELASTICAPM_LOG_CUSTOM_SINK_FUNC( String fullText );

static
void buildFullTextAndWriteToCustomSink( Logger* logger, StringView commonPrefix, String msgFmt, va_list msgArgs )
{
    char sinkSpecificPrefixBuffer[ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];

    String fullText = concatPrefixAndMsg(
            logger
            , /* sinkSpecificPrefix: */ buildPrefixForSinkMixedWithOtherProcesses( sinkSpecificPrefixBuffer, ELASTICAPM_STATIC_ARRAY_SIZE( sinkSpecificPrefixBuffer ) )
            , /* sinkSpecificEndOfLine: */ ELASTICAPM_STRING_LITERAL_TO_VIEW( "" )
            , commonPrefix
            , /* prefixNewLines: */ false
            , msgFmt
            , msgArgs );

    ELASTICAPM_LOG_CUSTOM_SINK_FUNC( fullText );
}

#endif // #ifdef ELASTICAPM_LOG_CUSTOM_SINK_FUNC

void logWithLogger(
        Logger* logger
        , bool isForced
        , LogLevel statementLevel
        , StringView category
        , StringView filePath
        , UInt lineNumber
        , StringView funcName
        , String msgPrintfFmt
        , ...
)
{
    va_list msgPrintfFmtArgs;
            va_start( msgPrintfFmtArgs, msgPrintfFmt );
    vLogWithLogger( logger
                    , isForced
                    , statementLevel
                    , category
                    , filePath
                    , lineNumber
                    , funcName
                    , msgPrintfFmt
                    , msgPrintfFmtArgs );
            va_end( msgPrintfFmtArgs );
}

void vLogWithLogger(
        Logger* logger
        , bool isForced
        , LogLevel statementLevel
        , StringView category
        , StringView filePath
        , UInt lineNumber
        , StringView funcName
        , String msgPrintfFmt
        , va_list msgPrintfFmtArgs
)
{
    if ( logger->reentrancyDepth + 1 > maxLoggerReentrancyDepth ) return;
    ++ logger->reentrancyDepth;
    ELASTICAPM_ASSERT_GT_UINT64( logger->reentrancyDepth, 0 );

    ELASTICAPM_ASSERT_VALID_PTR( logger );

    enum
    {
        commonPrefixBufferSize = 200 + ELASTICAPM_TEXT_OUTPUT_STREAM_RESERVED_SPACE_SIZE,
    };
    char commonPrefixBuffer[commonPrefixBufferSize];

    StringView commonPrefix = buildCommonPrefix( statementLevel, category, filePath, lineNumber, funcName, commonPrefixBuffer, commonPrefixBufferSize );

    if ( isForced || logger->config.levelPerSinkType[ logSink_stderr ] >= statementLevel )
    {
        // create a separate copy of va_list because functions using it (such as fprintf, etc.) modify it
        va_list msgPrintfFmtArgsCopy;
        va_copy( /* dst: */ msgPrintfFmtArgsCopy, /* src: */ msgPrintfFmtArgs );
        writeToStderr( logger, statementLevel, commonPrefix, msgPrintfFmt, msgPrintfFmtArgsCopy );
        fflush( stderr );
                va_end( msgPrintfFmtArgsCopy );
    }

    #ifndef PHP_WIN32
    if ( isForced || logger->config.levelPerSinkType[ logSink_syslog ] >= statementLevel )
    {
        // create a separate copy of va_list because functions using it (such as fprintf, etc.) modify it
        va_list msgPrintfFmtArgsCopy;
        va_copy( /* dst: */ msgPrintfFmtArgsCopy, /* src: */ msgPrintfFmtArgs );
        writeToSyslog( logger, statementLevel, commonPrefix, msgPrintfFmt, msgPrintfFmtArgsCopy );
        va_end( msgPrintfFmtArgsCopy );
    }
    #endif

    #ifdef PHP_WIN32
    if ( isForced || logger->config.levelPerSinkType[ logSink_winSysDebug ] >= statementLevel )
    {
        // create a separate copy of va_list because functions using it (such as fprintf, etc.) modify it
        va_list msgPrintfFmtArgsCopy;
        va_copy( /* dst: */ msgPrintfFmtArgsCopy, /* src: */ msgPrintfFmtArgs );
        writeToWinSysDebug( logger, commonPrefix, msgPrintfFmt, msgPrintfFmtArgsCopy );
        va_end( msgPrintfFmtArgsCopy );
    }
            #endif

        // TODO: Sergey Kleyman: Implement log to file
//    if ( isForced || logger->config.levelPerSinkType[ logSink_file ] >= statementLevel )
//    {
//        // create a separate copy of va_list because functions using it (such as fprintf, etc.) modify it
//        va_list msgPrintfFmtArgsCopy;
//        va_copy( /* dst: */ msgPrintfFmtArgsCopy, /* src: */ msgPrintfFmtArgs );
//        writeToFile( logger, commonPrefix, msgPrintfFmt, msgPrintfFmtArgsCopy );
//        va_end( msgPrintfFmtArgsCopy );
//    }

#ifdef ELASTICAPM_LOG_CUSTOM_SINK_FUNC
        va_list msgPrintfFmtArgsCopy;
        va_copy( /* dst: */ msgPrintfFmtArgsCopy, /* src: */ msgPrintfFmtArgs );
        buildFullTextAndWriteToCustomSink( logger, commonPrefix, msgPrintfFmt, msgPrintfFmtArgsCopy );
        va_end( msgPrintfFmtArgsCopy );
#endif

    ELASTICAPM_ASSERT_GT_UINT64( logger->reentrancyDepth, 0 );
    -- logger->reentrancyDepth;
}

static
LogLevel findMaxLevel( const LogLevel* levelsArray, size_t levelsArraySize, LogLevel minLevel )
{
    int max = minLevel;
    ELASTICAPM_FOR_EACH_INDEX( i, levelsArraySize ) if ( levelsArray[ i ] > max ) max = levelsArray[ i ];
    return max;
}

LogLevel calcMaxEnabledLogLevel( LogLevel levelPerSinkType[numberOfLogSinkTypes] )
{
    return findMaxLevel( levelPerSinkType, numberOfLogSinkTypes, /* minValue */ logLevel_not_set );
}

static
void setLoggerConfigToDefaults( LoggerConfig* config )
{
    ELASTICAPM_FOR_EACH_INDEX( sinkTypeIndex, numberOfLogSinkTypes )config->levelPerSinkType[ sinkTypeIndex ] = defaultLogLevelPerSinkType[ sinkTypeIndex ];

    config->file = NULL;
}

static
LogLevel deriveLevelForSink( LogLevel levelForSink, LogLevel generalLevel, LogLevel defaultLevelForSink )
{
    if ( levelForSink != logLevel_not_set ) return levelForSink;
    if ( generalLevel != logLevel_not_set ) return generalLevel;
    return defaultLevelForSink;
}

static void deriveLoggerConfig( const LoggerConfig* newConfig, LogLevel generalLevel, LoggerConfig* derivedNewConfig )
{
    LoggerConfig defaultConfig;

    setLoggerConfigToDefaults( &defaultConfig );

    ELASTICAPM_FOR_EACH_LOG_SINK_TYPE( logSinkType )
    {
        derivedNewConfig->levelPerSinkType[ logSinkType ] = deriveLevelForSink(
                newConfig->levelPerSinkType[ logSinkType ], generalLevel, defaultConfig.levelPerSinkType[ logSinkType ] );
    }
}

static bool areEqualLoggerConfigs( const LoggerConfig* config1, const LoggerConfig* config2 )
{
    ELASTICAPM_FOR_EACH_LOG_SINK_TYPE( logSinkType )if ( config1->levelPerSinkType[ logSinkType ] != config2->levelPerSinkType[ logSinkType ] ) return false;

    if ( ! areEqualNullableStrings( config1->file, config2->file ) ) return false;

    return true;
}

static void logConfigChangeInLevel( String dbgLevelDesc, LogLevel oldLevel, LogLevel newLevel )
{
    char txtOutStreamBuf[ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    if ( oldLevel == newLevel )
        ELASTICAPM_LOG_DEBUG( "%s did not change. Its value is still %s."
                              , dbgLevelDesc
                              , streamLogLevel( oldLevel, &txtOutStream ) );
    else
        ELASTICAPM_LOG_DEBUG( "%s changed from %s to %s."
                              , dbgLevelDesc
                              , streamLogLevel( oldLevel, &txtOutStream )
                              , streamLogLevel( newLevel, &txtOutStream ) );
}

String logSinkTypeName[numberOfLogSinkTypes] =
        {
                [logSink_stderr] = "Stderr",
                #ifndef PHP_WIN32
                [ logSink_syslog ] = "Syslog",
                #endif

                #ifdef PHP_WIN32
                [logSink_winSysDebug] = "Windows system debugger",
                #endif

                [logSink_file] = "File"
        };

LogLevel defaultLogLevelPerSinkType[numberOfLogSinkTypes] =
        {
                [logSink_stderr] = logLevel_critical,
                #ifndef PHP_WIN32
                [ logSink_syslog ] = logLevel_error,
                #endif

                #ifdef PHP_WIN32
                [logSink_winSysDebug] = logLevel_debug,
                #endif

                [logSink_file] = logLevel_info
        };

static void logConfigChange(
        const LoggerConfig* oldConfig, LogLevel oldMaxEnabledLevel, const LoggerConfig* newConfig, LogLevel newMaxEnabledLevel
)
{
    char txtOutStreamBuf[ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTICAPM_FOR_EACH_LOG_SINK_TYPE( logSinkType )
    {
        textOutputStreamRewind( &txtOutStream );
        logConfigChangeInLevel( streamPrintf( &txtOutStream, "Log level for sink %s", logSinkTypeName[ logSinkType ] )
                                , oldConfig->levelPerSinkType[ logSinkType ]
                                , newConfig->levelPerSinkType[ logSinkType ] );
    }

    textOutputStreamRewind( &txtOutStream );
    logConfigChangeInLevel( "Max enabled log level", oldMaxEnabledLevel, newMaxEnabledLevel );

    textOutputStreamRewind( &txtOutStream );
    if ( areEqualNullableStrings( oldConfig->file, newConfig->file ) )
        ELASTICAPM_LOG_DEBUG( "Path for file logging sink did not change. Its value is still %s."
                              , streamUserString( newConfig->file, &txtOutStream ) );
    else
        ELASTICAPM_LOG_DEBUG( "Path for file logging sink changed from %s to %s."
                              , streamUserString( oldConfig->file, &txtOutStream )
                              , streamUserString( newConfig->file, &txtOutStream ) );
}

void reconfigureLogger( Logger* logger, const LoggerConfig* newConfig, LogLevel generalLevel )
{
    LoggerConfig derivedNewConfig = *newConfig;
    deriveLoggerConfig( newConfig, generalLevel, &derivedNewConfig );

    if ( areEqualLoggerConfigs( &logger->config, &derivedNewConfig ) )
    {
        ELASTICAPM_LOG_DEBUG( "Logger configuration did not change" );
        return;
    }

    const LoggerConfig oldConfig = logger->config;
    const LogLevel oldMaxEnabledLevel = logger->maxEnabledLevel;
    logger->config = derivedNewConfig;
    logger->maxEnabledLevel = calcMaxEnabledLogLevel( logger->config.levelPerSinkType );
    logConfigChange( &oldConfig, oldMaxEnabledLevel, &logger->config, logger->maxEnabledLevel );
}

ResultCode constructLogger( Logger* logger )
{
    ELASTICAPM_ASSERT_VALID_PTR( logger );

    ResultCode resultCode;

    setLoggerConfigToDefaults( &( logger->config ) );
    logger->maxEnabledLevel = calcMaxEnabledLogLevel( logger->config.levelPerSinkType );
    logger->messageBuffer = NULL;
    logger->auxMessageBuffer = NULL;
    logger->fileFailed = false;

    ELASTICAPM_PEMALLOC_STRING_IF_FAILED_GOTO( loggerMessageBufferSize, logger->messageBuffer );
    ELASTICAPM_PEMALLOC_STRING_IF_FAILED_GOTO( loggerMessageBufferSize, logger->auxMessageBuffer );

    resultCode = resultSuccess;

    finally:
    return resultCode;

    failure:
    destructLogger( logger );
    goto finally;
}

void destructLogger( Logger* logger )
{
    ELASTICAPM_ASSERT_VALID_PTR( logger );

    ELASTICAPM_PEFREE_STRING_AND_SET_TO_NULL( loggerMessageBufferSize, logger->auxMessageBuffer );
    ELASTICAPM_PEFREE_STRING_AND_SET_TO_NULL( loggerMessageBufferSize, logger->messageBuffer );
}

Logger* getGlobalLogger()
{
    return &getGlobalTracer()->logger;
}
