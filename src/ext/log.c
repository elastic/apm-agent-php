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

#include "log.h"
#include <stdio.h>
#include <stdarg.h>
#ifndef PHP_WIN32
#   include <syslog.h>
#   include <unistd.h>
#   include <fcntl.h>
#endif
#include "elastic_apm_clock.h"
#include "util.h"
#include "elastic_apm_alloc.h"
#include "platform.h"
#include "TextOutputStream.h"
#include "Tracer.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_LOG

#ifndef PHP_WIN32
LogLevel g_elasticApmDirectLogLevelSyslog = logLevel_off;
#endif // #ifndef PHP_WIN32
LogLevel g_elasticApmDirectLogLevelStderr = logLevel_off;

String logLevelNames[numberOfLogLevels] =
        {
                [logLevel_off] = "OFF", [logLevel_critical] = "CRITICAL", [logLevel_error] = "ERROR", [logLevel_warning] = "WARNING", [logLevel_info] = "INFO", [logLevel_debug] = "DEBUG", [logLevel_trace] = "TRACE"
        };

const char* logLevelToName( LogLevel level )
{
    if ( ELASTIC_APM_IS_IN_END_EXCLUDED_RANGE( logLevel_off, level, numberOfLogLevels ) )
    {
        return logLevelNames[ level ];
    }

    return "UNKNOWN";
}

enum
{
    loggerMessageBufferSize = 1000 * 1000 + 1
};

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

static const char logLinePartsSeparator[] = " ";

static void appendSeparator( TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    streamStringView( ELASTIC_APM_STRING_LITERAL_TO_VIEW( logLinePartsSeparator ), txtOutStream );
}

#define ELASTIC_APM_LOG_FMT_PAD_START ""

#define ELASTIC_APM_LOG_FMT_NUMBER_PAD_START( minWidth ) \
    "%" ELASTIC_APM_LOG_FMT_PAD_START ELASTIC_APM_PP_STRINGIZE( minWidth ) "d"

static
void streamPadding( Int64 paddingLength, TextOutputStream* txtOutStream )
{
    if ( paddingLength <= 0 ) return;
    ELASTIC_APM_REPEAT_N_TIMES( ((size_t)paddingLength) ) streamChar( ' ', txtOutStream );
}

static
void appendLevel( LogLevel level, TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    const char* posBeforeWrite = textOutputStreamGetFreeSpaceBegin( txtOutStream );
    streamChar( '[', txtOutStream );
    if ( level < numberOfLogLevels )
    {
        // if it's a level with a name
        streamPrintf( txtOutStream, "%s", logLevelToName( level ) );
    }
    else
    {
        // otherwise print it as a number
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
    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    streamChar( '[', txtOutStream );
    streamStringView( category, txtOutStream );
    streamChar( ']', txtOutStream );
}

static void appendProcessThreadIds( TextOutputStream* txtOutStream )
{
    // [PID: 12345] [TID: 67890]

    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    pid_t processId = getCurrentProcessId();

    streamStringView( ELASTIC_APM_STRING_LITERAL_TO_VIEW("[PID: "), txtOutStream );
    streamPrintf( txtOutStream, "%u", processId );
    streamChar( ']', txtOutStream );

    appendSeparator( txtOutStream );

    pid_t threadId = getCurrentThreadId();

    streamStringView( ELASTIC_APM_STRING_LITERAL_TO_VIEW("[TID: "), txtOutStream );
    streamPrintf( txtOutStream, "%u", threadId );
    streamChar( ']', txtOutStream );
}

static
void appendFileNameLineNumberPart( StringView filePath, UInt lineNumber, TextOutputStream* txtOutStream )
{
    // lifecycle.c:482

    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    streamChar( '[', txtOutStream );
    streamStringView( extractLastPartOfFilePathStringView( filePath ), txtOutStream );
    streamChar( ':', txtOutStream );
    streamPrintf( txtOutStream, "%u", lineNumber );
    streamChar( ']', txtOutStream );
}

static void appendFunctionName( StringView funcName, TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( txtOutStream );

    streamChar( '[', txtOutStream );
    streamStringView( funcName, txtOutStream );
    streamChar( ']', txtOutStream );
}

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
    // 2020-05-08 08:18:54.154244+02:00 [PID:12345] [TID:12345] [DEBUG]    [Configuration] [ConfigManager.c:1127] [ensureConfigManagerHasLatestConfig] Current configuration is already the latest [TransactionId: xyz] [namespace: Impl\AutoInstrument]
    // ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

    TextOutputStream txtOutStream = makeTextOutputStream( buffer, bufferSize );
    // We don't need terminating '\0' after the prefix because we return it as StringView
    txtOutStream.autoTermZero = false;
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( &txtOutStream, &txtOutStreamStateOnEntryStart ) )
    {
        return ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER );
    }

    streamCurrentLocalTime( &txtOutStream );
    appendSeparator( &txtOutStream );
    appendProcessThreadIds( &txtOutStream );
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

StringView insertPrefixAtEachNewLine(
        Logger* logger
        , StringView sinkSpecificPrefix
        , StringView commonPrefix
        , StringView oldMessage
        , size_t maxSizeForNewMessage
)
{
    ELASTIC_APM_ASSERT_VALID_PTR( logger->auxMessageBuffer );
    ELASTIC_APM_ASSERT_LE_UINT64( maxSizeForNewMessage, loggerMessageBufferSize );

    TextOutputStream txtOutStream = makeTextOutputStream( logger->auxMessageBuffer, maxSizeForNewMessage );
    // We don't need terminating '\0' after the prefix because we return it as StringView
    txtOutStream.autoTermZero = false;
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( &txtOutStream, &txtOutStreamStateOnEntryStart ) )
    {
        return ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER );
    }

    const char* oldMessageEnd = stringViewEnd( oldMessage );
    StringView oldMessageLeft = oldMessage;
    for ( ;; )
    {
        StringView eolSeq = findEndOfLineSequence( oldMessageLeft );
        if ( isEmptyStringView( eolSeq ) ) break;

        streamStringView( makeStringViewFromBeginEnd( oldMessageLeft.begin, stringViewEnd( eolSeq ) ), &txtOutStream );
        if ( sinkSpecificPrefix.length != 0 )
        {
            streamStringView( sinkSpecificPrefix, &txtOutStream );
            appendSeparator( &txtOutStream );
        }
        streamStringView( commonPrefix, &txtOutStream );
        appendSeparator( &txtOutStream );
        oldMessageLeft = makeStringViewFromBeginEnd( stringViewEnd( eolSeq ), oldMessageEnd );
    }

    // If we didn't write anything to new message part then it means the old one is just one line
    // so there's no need to insert any prefixes
    if ( isEmptyStringView( textOutputStreamContentAsStringView( &txtOutStream ) ) ) return makeEmptyStringView();

    streamStringView( oldMessageLeft, &txtOutStream );

    textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, &txtOutStream );
    return textOutputStreamContentAsStringView( &txtOutStream );
}

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
    ELASTIC_APM_ASSERT_VALID_PTR( logger );
    ELASTIC_APM_ASSERT_VALID_PTR( logger->messageBuffer );

    TextOutputStream txtOutStream = makeTextOutputStream( logger->messageBuffer, loggerMessageBufferSize );
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( &txtOutStream, &txtOutStreamStateOnEntryStart ) )
    {
        return ELASTIC_APM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;
    }

    if ( sinkSpecificPrefix.length != 0 )
    {
        streamStringView( sinkSpecificPrefix, &txtOutStream );
        appendSeparator( &txtOutStream );
    }
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

void writeToStderr( Logger* logger, LogLevel statementLevel, StringView commonPrefix, String msgFmt, va_list msgArgs )
{
    String fullText = concatPrefixAndMsg(
            logger
            , /* sinkSpecificPrefix: */ ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_LOG_LINE_PREFIX_TRACER_PART )
            , /* sinkSpecificEndOfLine: */ ELASTIC_APM_STRING_LITERAL_TO_VIEW( "\n" )
            , commonPrefix
            , /* prefixNewLines: */ true
            , msgFmt
            , msgArgs );

    fprintf( stderr, "%s", fullText );
    fflush( stderr );
}

#ifndef PHP_WIN32 // syslog is not supported on Windows
//////////////////////////////////////////////////////////////////////////////
//
// syslog
//
int logLevelToSyslog( LogLevel level )
{
    switch ( level )
    {
        case logLevel_trace: // NOLINT(bugprone-branch-clone)
        case logLevel_debug:
            return LOG_DEBUG;

        case logLevel_info:
            return LOG_INFO;

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

void writeToSyslog( Logger* logger, LogLevel level, StringView commonPrefix, String msgFmt, va_list msgArgs )
{
    char sinkSpecificPrefixBuffer[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];

    String fullText = concatPrefixAndMsg(
            logger
            , /* sinkSpecificPrefix: */ ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_LOG_LINE_PREFIX_TRACER_PART )
            , /* sinkSpecificEndOfLine: */ ELASTIC_APM_STRING_LITERAL_TO_VIEW( "" )
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
    char sinkSpecificPrefixBuffer[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];

    String fullText = concatPrefixAndMsg(
            logger
            , /* sinkSpecificPrefix: */ ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_LOG_LINE_PREFIX_TRACER_PART )
            , /* sinkSpecificEndOfLine: */ ELASTIC_APM_STRING_LITERAL_TO_VIEW( "\n" )
            , commonPrefix
            , /* prefixNewLines: */ true
            , msgFmt
            , msgArgs );

    writeToWindowsSystemDebugger( fullText );
}
#endif

static void openAndAppendToFile( Logger* logger, String text )
{
    size_t textLen = strlen( text );

// TODO: Sergey Kleyman: Uncomment: Fix lower level system calls - "open" and "write" to get stronger guarantee
//#ifdef PHP_WIN32
    FILE* file = fopen( logger->config.file, "a" );
    if ( file == NULL )
    {
        goto failure;
    }
    size_t numberOfElementsWritten = fwrite( text, sizeof( *text ), textLen, file );
    if ( numberOfElementsWritten != textLen )
    {
        goto failure;
    }
//#else
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
//    if ( file < 0 )
//    {
//        goto failure;
//    }
//    size_t numberOfBytesToWrite = ( (long) sizeof( *text ) ) * textLen;
//    ssize_t numberOfBytesWritten = write( file, text, numberOfBytesToWrite );
//    if ( numberOfBytesWritten != numberOfBytesToWrite )
//    {
//        goto failure;
//    }
//#endif

    finally:
//#ifdef PHP_WIN32
    if ( file != NULL )
    {
        fclose( file );
    }
//#else
//    if ( file >= 0 )
//    {
//        close( file );
//    }
//#endif

    return;

    failure:
    logger->fileFailed = true;
    goto finally;
}

static
bool isLogFileInGoodState( Logger* logger )
{
    return ( ! isNullOrEmtpyString( logger->config.file ) ) && ( ! logger->fileFailed );
}

void writeToFile( Logger* logger, StringView commonPrefix, String msgFmt, va_list msgArgs )
{
    ELASTIC_APM_ASSERT( isLogFileInGoodState( logger ), "" );

    String fullText = concatPrefixAndMsg(
            logger
            , /* sinkSpecificPrefix: */ ELASTIC_APM_STRING_LITERAL_TO_VIEW( "" )
            , /* sinkSpecificEndOfLine: */ ELASTIC_APM_STRING_LITERAL_TO_VIEW( "\n" )
            , commonPrefix
            , /* prefixNewLines: */ true
            , msgFmt
            , msgArgs );

    openAndAppendToFile( logger, fullText );
}

#ifdef ELASTIC_APM_LOG_CUSTOM_SINK_FUNC

// Declare to avoid warnings
void ELASTIC_APM_LOG_CUSTOM_SINK_FUNC( String fullText );

static
void buildFullTextAndWriteToCustomSink( Logger* logger, StringView commonPrefix, String msgFmt, va_list msgArgs )
{
    char sinkSpecificPrefixBuffer[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];

    String fullText = concatPrefixAndMsg(
            logger
            , /* sinkSpecificPrefix: */ ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_LOG_LINE_PREFIX_TRACER_PART )
            , /* sinkSpecificEndOfLine: */ ELASTIC_APM_STRING_LITERAL_TO_VIEW( "" )
            , commonPrefix
            , /* prefixNewLines: */ false
            , msgFmt
            , msgArgs );

    ELASTIC_APM_LOG_CUSTOM_SINK_FUNC( fullText );
}

#endif // #ifdef ELASTIC_APM_LOG_CUSTOM_SINK_FUNC

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

void vLogWithLoggerImpl(
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
    ELASTIC_APM_ASSERT_GT_UINT64( logger->reentrancyDepth, 0 );

    ELASTIC_APM_ASSERT_VALID_PTR( logger );

    enum
    {
        commonPrefixBufferSize = 200 + ELASTIC_APM_TEXT_OUTPUT_STREAM_RESERVED_SPACE_SIZE,
    };
    char commonPrefixBuffer[commonPrefixBufferSize];

    StringView commonPrefix = buildCommonPrefix( statementLevel, category, filePath, lineNumber, funcName, commonPrefixBuffer, commonPrefixBufferSize );

    if ( isForced || logger->config.levelPerSinkType[ logSink_stderr ] >= statementLevel )
    {
        // create a separate copy of va_list because functions using it (such as fprintf, etc.) modify it
        va_list msgPrintfFmtArgsCopy;
        va_copy( /* dst: */ msgPrintfFmtArgsCopy, /* src: */ msgPrintfFmtArgs );
        writeToStderr( logger, statementLevel, commonPrefix, msgPrintfFmt, msgPrintfFmtArgsCopy );
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

    if ( ( isForced || logger->config.levelPerSinkType[ logSink_file ] >= statementLevel ) && isLogFileInGoodState( logger ) )
    {
        // create a separate copy of va_list because functions using it (such as fprintf, etc.) modify it
        va_list msgPrintfFmtArgsCopy;
        va_copy( /* dst: */ msgPrintfFmtArgsCopy, /* src: */ msgPrintfFmtArgs );
        writeToFile( logger, commonPrefix, msgPrintfFmt, msgPrintfFmtArgsCopy );
        va_end( msgPrintfFmtArgsCopy );
    }

#ifdef ELASTIC_APM_LOG_CUSTOM_SINK_FUNC
        va_list msgPrintfFmtArgsCopy;
        va_copy( /* dst: */ msgPrintfFmtArgsCopy, /* src: */ msgPrintfFmtArgs );
        buildFullTextAndWriteToCustomSink( logger, commonPrefix, msgPrintfFmt, msgPrintfFmtArgsCopy );
        va_end( msgPrintfFmtArgsCopy );
#endif

    ELASTIC_APM_ASSERT_GT_UINT64( logger->reentrancyDepth, 0 );
    -- logger->reentrancyDepth;
}


static String g_logMutexDesc = "global logger";
static Mutex* g_logMutex = NULL;
static __thread bool g_isInLogContext = false;

bool isInLogContext()
{
    return g_isInLogContext;
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
    if ( g_logMutex == NULL )
    {
        #ifndef PHP_WIN32
        ELASTIC_APM_LOG_DIRECT_CRITICAL( "g_logMutex is NULL; filePath: %.*s, lineNumber: %d, funcName: %.*s, msgPrintfFmt: %s"
                                         , (int)filePath.length, filePath.begin, lineNumber, (int)funcName.length, funcName.begin, msgPrintfFmt );
        #endif
        return;
    }

    if ( g_isInLogContext )
    {
        #ifndef PHP_WIN32
        ELASTIC_APM_LOG_DIRECT_CRITICAL( "Trying to re-enter logging; filePath: %.*s, lineNumber: %d, funcName: %.*s, msgPrintfFmt: %s"
                                         , (int)filePath.length, filePath.begin, lineNumber, (int)funcName.length, funcName.begin, msgPrintfFmt );
        #endif
        return;
    }

    g_isInLogContext = true;

    bool shouldUnlockMutex = false;
    // Don't log for logging mutex to avoid spamming the log
    ResultCode resultCode = lockMutexNoLogging( g_logMutex, &shouldUnlockMutex, __FUNCTION__ );
    if ( resultCode != resultSuccess )
    {
        ELASTIC_APM_LOG_DIRECT_CRITICAL( "Failed to lock g_logMutex, resultCode: %s (%d); filePath: %.*s, lineNumber: %d, funcName: %.*s, msgPrintfFmt: %s"
                                         , resultCodeToString( resultCode ), resultCode, (int)filePath.length, filePath.begin, lineNumber, (int)funcName.length, funcName.begin, msgPrintfFmt );
        goto finally;
    }

    vLogWithLoggerImpl( logger
                        , isForced
                        , statementLevel
                        , category
                        , filePath
                        , lineNumber
                        , funcName
                        , msgPrintfFmt
                        , msgPrintfFmtArgs );

    finally:
    // Don't log for logging mutex to avoid spamming the log
    unlockMutexNoLogging( g_logMutex, &shouldUnlockMutex, __FUNCTION__ );
    g_isInLogContext = false;
}

static
LogLevel findMaxLevel( const LogLevel* levelsArray, size_t levelsArraySize, LogLevel minLevel )
{
    int max = minLevel;
    ELASTIC_APM_FOR_EACH_INDEX( i, levelsArraySize ) if ( levelsArray[ i ] > max ) max = levelsArray[ i ];
    return max;
}

LogLevel calcMaxEnabledLogLevel( LogLevel levelPerSinkType[numberOfLogSinkTypes] )
{
    return findMaxLevel( levelPerSinkType, numberOfLogSinkTypes, /* minValue */ logLevel_not_set );
}

static
void setLoggerConfigToDefaults( LoggerConfig* config )
{
    ELASTIC_APM_FOR_EACH_INDEX( sinkTypeIndex, numberOfLogSinkTypes )config->levelPerSinkType[ sinkTypeIndex ] = defaultLogLevelPerSinkType[ sinkTypeIndex ];

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

    ELASTIC_APM_FOR_EACH_LOG_SINK_TYPE( logSinkType )
    {
        derivedNewConfig->levelPerSinkType[ logSinkType ] = deriveLevelForSink(
                newConfig->levelPerSinkType[ logSinkType ], generalLevel, defaultConfig.levelPerSinkType[ logSinkType ] );
    }
}

static bool areEqualLoggerConfigs( const LoggerConfig* config1, const LoggerConfig* config2 )
{
    ELASTIC_APM_FOR_EACH_LOG_SINK_TYPE( logSinkType )if ( config1->levelPerSinkType[ logSinkType ] != config2->levelPerSinkType[ logSinkType ] ) return false;

    if ( ! areEqualNullableStrings( config1->file, config2->file ) ) return false;

    return true;
}

static void logConfigChangeInLevel( String dbgLevelDesc, LogLevel oldLevel, LogLevel newLevel )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    if ( oldLevel == newLevel )
        ELASTIC_APM_LOG_DEBUG( "%s did not change. Its value is still %s."
                              , dbgLevelDesc
                              , streamLogLevel( oldLevel, &txtOutStream ) );
    else
        ELASTIC_APM_LOG_DEBUG( "%s changed from %s to %s."
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
                [ logSink_syslog ] = logLevel_info,
                #endif

                #ifdef PHP_WIN32
                [logSink_winSysDebug] = logLevel_debug,
                #endif

                [logSink_file] = logLevel_info
        };

static void logConfigChange(
    const LoggerConfig* oldConfig,
    LogLevel oldMaxEnabledLevel,
    const LoggerConfig* newConfig,
    LogLevel newMaxEnabledLevel
)
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_FOR_EACH_LOG_SINK_TYPE( logSinkType )
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
        ELASTIC_APM_LOG_DEBUG( "Path for file logging sink did not change. Its value is still %s."
                              , streamUserString( newConfig->file, &txtOutStream ) );
    else
        ELASTIC_APM_LOG_DEBUG( "Path for file logging sink changed from %s to %s."
                              , streamUserString( oldConfig->file, &txtOutStream )
                              , streamUserString( newConfig->file, &txtOutStream ) );
}

void destructLoggerConfig( LoggerConfig* loggerConfig )
{
    ELASTIC_APM_ASSERT_VALID_PTR( loggerConfig );

    ELASTIC_APM_PEFREE_STRING_AND_SET_TO_NULL( loggerConfig->file );
}

ResultCode reconfigureLogger( Logger* logger, const LoggerConfig* newConfig, LogLevel generalLevel )
{
    ResultCode resultCode;
    LoggerConfig derivedNewConfig = *newConfig;
    String filePathCopy = NULL;
    deriveLoggerConfig( newConfig, generalLevel, &derivedNewConfig );

    if ( areEqualLoggerConfigs( &logger->config, &derivedNewConfig ) )
    {
        ELASTIC_APM_LOG_DEBUG( "Logger configuration did not change" );
        resultCode = resultSuccess;
        goto finally;
    }

    if ( newConfig->file != NULL )
    {
        ELASTIC_APM_PEMALLOC_DUP_STRING_IF_FAILED_GOTO( newConfig->file, /* out */ filePathCopy );
    }

    LoggerConfig oldConfig = logger->config;
    const LogLevel oldMaxEnabledLevel = logger->maxEnabledLevel;
    logger->config = derivedNewConfig;
    logger->config.file = filePathCopy;
    filePathCopy = NULL;
    logger->maxEnabledLevel = calcMaxEnabledLogLevel( logger->config.levelPerSinkType );
    logConfigChange( &oldConfig, oldMaxEnabledLevel, &logger->config, logger->maxEnabledLevel );

#   ifndef PHP_WIN32
    g_elasticApmDirectLogLevelSyslog = logger->config.levelPerSinkType[ logSink_syslog ];
#   endif // #ifndef PHP_WIN32
    g_elasticApmDirectLogLevelStderr = logger->config.levelPerSinkType[ logSink_stderr ];

    destructLoggerConfig( &oldConfig );
    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    ELASTIC_APM_PEFREE_STRING_AND_SET_TO_NULL( filePathCopy );
    goto finally;
}

ResultCode constructLogger( Logger* logger )
{
    ELASTIC_APM_ASSERT_VALID_PTR( logger );

    ResultCode resultCode;

    if ( g_logMutex == NULL )
    {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( newMutex( &g_logMutex, g_logMutexDesc ) );
    }

    setLoggerConfigToDefaults( &( logger->config ) );
    logger->maxEnabledLevel = calcMaxEnabledLogLevel( logger->config.levelPerSinkType );
    logger->messageBuffer = NULL;
    logger->auxMessageBuffer = NULL;
    logger->fileFailed = false;

    ELASTIC_APM_PEMALLOC_STRING_IF_FAILED_GOTO( loggerMessageBufferSize, logger->messageBuffer );
    ELASTIC_APM_PEMALLOC_STRING_IF_FAILED_GOTO( loggerMessageBufferSize, logger->auxMessageBuffer );

    resultCode = resultSuccess;

    finally:
    return resultCode;

    failure:
    destructLogger( logger );
    goto finally;
}

void destructLogger( Logger* logger )
{
    ELASTIC_APM_ASSERT_VALID_PTR( logger );

    destructLoggerConfig( &( logger->config ) );
    ELASTIC_APM_PEFREE_STRING_SIZE_AND_SET_TO_NULL( loggerMessageBufferSize, logger->auxMessageBuffer );
    ELASTIC_APM_PEFREE_STRING_SIZE_AND_SET_TO_NULL( loggerMessageBufferSize, logger->messageBuffer );

    if ( g_logMutex != NULL )
    {
        deleteMutex( &g_logMutex );
    }
}

Logger* getGlobalLogger()
{
    return &getGlobalTracer()->logger;
}

ResultCode resetLoggingStateInForkedChild()
{
    // We SHOULD NOT log before resetting state because logging uses thread synchronization
    // which might deadlock in forked child

    ResultCode resultCode;

    g_isInLogContext = true;

    if ( g_logMutex != NULL )
    {
        deleteMutex( &g_logMutex );
        ELASTIC_APM_CALL_IF_FAILED_GOTO( newMutex( &g_logMutex, g_logMutexDesc ) );
    }

    resultCode = resultSuccess;

    finally:
    g_isInLogContext = false;
    return resultCode;

    failure:
    goto finally;
}