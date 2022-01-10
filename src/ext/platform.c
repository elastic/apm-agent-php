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

#include "platform.h"
#include <limits.h>
#include <string.h>
#ifdef PHP_WIN32
#   ifndef WIN32_LEAN_AND_MEAN
#       define WIN32_LEAN_AND_MEAN
#   endif
#   ifndef VC_EXTRALEAN
#       define VC_EXTRALEAN
#   endif
#   include <windows.h>
#   include <process.h>
#else
#   include <unistd.h>
#   include <sys/syscall.h>
#   include <syslog.h>
#   include <signal.h>
#endif
#include "util.h"
#include "log.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_PLATFORM

pid_t getCurrentProcessId()
{
    #ifdef PHP_WIN32

    return _getpid();

    #else

    return getpid();

    #endif
}

pid_t getCurrentThreadId()
{
    #ifdef PHP_WIN32

    return (pid_t) GetCurrentThreadId();

    #else

    return syscall( SYS_gettid );

    #endif
}

#ifdef PHP_WIN32
void writeToWindowsSystemDebugger( String msg )
{
    ELASTIC_APM_ASSERT_VALID_STRING( msg );

    OutputDebugStringA( msg );
}
#endif

#ifdef PHP_WIN32
bool getTimeZoneShiftOnWindows( long* secondsAheadUtc )
{
    ELASTIC_APM_ASSERT_VALID_PTR( secondsAheadUtc );

    TIME_ZONE_INFORMATION timeZoneInformation = { 0 };

    if ( GetTimeZoneInformation( &timeZoneInformation ) == TIME_ZONE_ID_INVALID ) return false;

    // https://docs.microsoft.com/en-ca/windows/win32/api/timezoneapi/ns-timezoneapi-time_zone_information
    // Bias is the difference, in minutes, between Coordinated Universal Time (UTC) and local time.
    // All translations between UTC and local time are based on the following formula:
    // UTC = local time + bias
    // So negative value in Bias means that we are to the east of UTC, so it is positive for our format

    *secondsAheadUtc = -( timeZoneInformation.Bias * 60 );

    return true;
}
#endif

String streamErrNo( int errnoValue, TextOutputStream* txtOutStream )
{
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( txtOutStream, &txtOutStreamStateOnEntryStart ) )
        return ELASTIC_APM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;

    const size_t freeSpaceSize = textOutputStreamGetFreeSpaceSize( txtOutStream );
    if ( freeSpaceSize == 0 ) return textOutputStreamEndEntryAsOverflowed( &txtOutStreamStateOnEntryStart, txtOutStream );

    // +1 to detect overflow and +1 for terminating '\0'
    const size_t freeSizePlus = freeSpaceSize + 2;
    ELASTIC_APM_ASSERT_VALID_OBJ( assertValidEndPtrIntoTextOutputStream( txtOutStreamStateOnEntryStart.freeSpaceBegin + freeSizePlus, txtOutStream ) );

    // Write terminating zero to detect if anything was written to the buffer
    // by the function converting errno to string
    *( txtOutStreamStateOnEntryStart.freeSpaceBegin ) = '\0';

    #ifdef PHP_WIN32

    // https://docs.microsoft.com/en-us/cpp/c-runtime-library/reference/strerror-s-strerror-s-wcserror-s-wcserror-s?view=vs-2019
    // strerror_s( char *buffer, size_t numberOfElements, int errnum );
    strerror_s( txtOutStreamStateOnEntryStart.freeSpaceBegin, freeSizePlus, errnoValue );

    #else

    // http://man7.org/linux/man-pages/man3/strerror.3.html
    // strerror_r( int errnum, char *buf, size_t buflen );
    strerror_r( errnoValue, txtOutStreamStateOnEntryStart.freeSpaceBegin, freeSizePlus );

    #endif

    const size_t numberOfContentChars = strlen( txtOutStreamStateOnEntryStart.freeSpaceBegin );
    const bool isOverflowed = ( numberOfContentChars > freeSpaceSize );
    textOutputStreamSkipNChars( txtOutStream, isOverflowed ? freeSpaceSize : numberOfContentChars );

    return ( isOverflowed )
           ? textOutputStreamEndEntryAsOverflowed( &txtOutStreamStateOnEntryStart, txtOutStream )
           : textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, txtOutStream );
}

#ifdef PHP_WIN32

size_t captureStackTraceWindows( void** addressesBuffer, size_t addressesBufferSize )
{
    ELASTIC_APM_UNUSED( addressesBuffer );
    ELASTIC_APM_UNUSED( addressesBufferSize );
    return 0;
}

String streamStackTraceWindows(
        void* const* addresses,
        size_t addressesCount,
        String linePrefix,
        TextOutputStream* txtOutStream )
{
    ELASTIC_APM_UNUSED( addresses );
    ELASTIC_APM_UNUSED( addressesCount );
    ELASTIC_APM_UNUSED( linePrefix );
    return streamString( "", txtOutStream );
}

#else // #ifdef PHP_WIN32

String streamStackTraceLinux(
        void* const* addresses,
        size_t addressesCount,
        String linePrefix,
        TextOutputStream* txtOutStream )
{
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( txtOutStream, &txtOutStreamStateOnEntryStart ) )
        return ELASTIC_APM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;

#ifdef ELASTIC_APM_PLATFORM_HAS_BACKTRACE

    char** addressesAsSymbols = backtrace_symbols( addresses, addressesCount );
    if ( addressesAsSymbols == NULL )
    {
        streamPrintf( txtOutStream, "backtrace_symbols returned NULL (i.e., failed to resolve addresses to symbols). Addresses:\n" );
        ELASTIC_APM_FOR_EACH_INDEX( i, addressesCount )
            streamPrintf( txtOutStream, "%s%p\n", linePrefix, addresses[ i ] );
    }
    else
    {
        ELASTIC_APM_FOR_EACH_INDEX( i, addressesCount )
            streamPrintf( txtOutStream, "%s%s\n", linePrefix, addressesAsSymbols[ i ] );

        free( addressesAsSymbols );
    }

#else

    streamPrintf( txtOutStream, "Could not obtain stack trace because execinfo/backtrace is not supported on this platform" );

#endif

    return textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, txtOutStream );
}

#endif // #else // #ifdef PHP_WIN32

String streamStackTrace(
        void* const* addresses,
        size_t addressesCount,
        String linePrefix,
        TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR( addresses );

    if ( addressesCount == 0 ) return streamString( "<stack is empty>", txtOutStream );

    return
        #ifdef PHP_WIN32
            streamStackTraceWindows( addresses, addressesCount, linePrefix, txtOutStream )
        #else
            streamStackTraceLinux( addresses, addressesCount, linePrefix, txtOutStream )
        #endif
            ;
}

#ifndef PHP_WIN32
static
String streamCurrentProcessCommandLineExHelper( unsigned int maxPartsCount, FILE* procSelfCmdLineFile, TextOutputStream* txtOutStream )
{
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( txtOutStream, &txtOutStreamStateOnEntryStart ) )
        return ELASTIC_APM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;

    txtOutStream->autoTermZero = false;

    enum { auxBufferSize = 100 };
    char auxBuffer[ auxBufferSize ];
    bool reachedEndOfFile = false;
    unsigned int partsCount = 0;
    bool shouldPrefixWithSpace = false;
    while ( ! reachedEndOfFile )
    {
        size_t actuallyReadBytes = fread( auxBuffer, /* data item size: */ 1, /* max data items count: */ auxBufferSize, procSelfCmdLineFile );
        if ( actuallyReadBytes < auxBufferSize)
        {
            if ( ferror( procSelfCmdLineFile ) != 0 )
            {
                return "Failed to read from /proc/self/cmdline";
            }

            reachedEndOfFile = ( feof( procSelfCmdLineFile ) != 0 );
            if ( ! reachedEndOfFile )
            {
                return "Failed to read full buffer from /proc/self/cmdline but feof() returned false";
            }
        }

        ELASTIC_APM_FOR_EACH_INDEX( i, actuallyReadBytes )
        {
            if ( auxBuffer[ i ] == '\0' )
            {
                ++partsCount;
                if ( partsCount == maxPartsCount )
                {
                    goto finally;
                }
                shouldPrefixWithSpace = true;
                continue;
            }

            if ( shouldPrefixWithSpace )
            {
                streamChar( ' ', txtOutStream );
                shouldPrefixWithSpace = false;
            }

            char bufferToEscape[ escapeNonPrintableCharBufferSize ];
            streamPrintf( txtOutStream, "%s", escapeNonPrintableChar( auxBuffer[ i ], bufferToEscape ) );
        }
    }

    finally:

    return textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, txtOutStream );
}
#endif

static
String streamCurrentProcessCommandLineEx( unsigned int maxPartsCount, TextOutputStream* txtOutStream )
{
    if ( maxPartsCount == 0 )
    {
        return "";
    }

#ifdef PHP_WIN32
    return "Not implemented on Windows";
#else
    FILE* procSelfCmdLineFile = procSelfCmdLineFile = fopen( "/proc/self/cmdline", "rb" );
    if ( procSelfCmdLineFile == NULL )
    {
        return "Failed to open /proc/self/cmdline";
    }

    String retVal = streamCurrentProcessCommandLineExHelper( maxPartsCount, procSelfCmdLineFile, txtOutStream );
    fclose( procSelfCmdLineFile );
    return retVal;
#endif
}

String streamCurrentProcessCommandLine( TextOutputStream* txtOutStream )
{
    return streamCurrentProcessCommandLineEx( /* maxPartsCount */ UINT_MAX, txtOutStream );
}

String streamCurrentProcessExeName( TextOutputStream* txtOutStream )
{
    return streamCurrentProcessCommandLineEx( /* maxPartsCount */ 1, txtOutStream );
}

#ifndef PHP_WIN32
static String osSignalIdToName( int signalId )
{
    #define ELASTIC_APM_OS_SIGNAL_ID_TO_NAME_SWITCH_CASE( sigIdForSwitchCase ) case sigIdForSwitchCase: return #sigIdForSwitchCase;

    switch ( signalId )
    {
        ELASTIC_APM_OS_SIGNAL_ID_TO_NAME_SWITCH_CASE( SIGQUIT )
        ELASTIC_APM_OS_SIGNAL_ID_TO_NAME_SWITCH_CASE( SIGABRT )
        ELASTIC_APM_OS_SIGNAL_ID_TO_NAME_SWITCH_CASE( SIGBUS )
        ELASTIC_APM_OS_SIGNAL_ID_TO_NAME_SWITCH_CASE( SIGKILL )
        ELASTIC_APM_OS_SIGNAL_ID_TO_NAME_SWITCH_CASE( SIGSEGV )
        ELASTIC_APM_OS_SIGNAL_ID_TO_NAME_SWITCH_CASE( SIGTERM )
        ELASTIC_APM_OS_SIGNAL_ID_TO_NAME_SWITCH_CASE( SIGSTOP )
        default: return "UNKNOWN OS SIGNAL ID";
    }

    #undef ELASTIC_APM_OS_SIGNAL_ID_TO_NAME_SWITCH_CASE
}

#define ELASTIC_APM_WRITE_FROM_SIGNAL_HANDLER( fmt, ... ) \
    do { \
        syslog( LOG_CRIT, ELASTIC_APM_LOG_LINE_PREFIX_TRACER_PART "[PID: %d] [CRITICAL] " fmt, getCurrentProcessId(), ##__VA_ARGS__ ); \
    } while ( 0 )


#if defined( ELASTIC_APM_PLATFORM_HAS_BACKTRACE )
void writeStackTraceToSyslog()
{
    enum { maxStackTraceAddressesCount = 100 };
    void* stackTraceAddresses[ maxStackTraceAddressesCount ];
    int stackTraceAddressesCount = backtrace( stackTraceAddresses, maxStackTraceAddressesCount );
    if ( stackTraceAddressesCount == 0 )
    {
        ELASTIC_APM_WRITE_FROM_SIGNAL_HANDLER( "backtrace returned 0 (i.e., failed to get any address on the stack)\n" );
        return;
    }

    ELASTIC_APM_FOR_EACH_INDEX( i, stackTraceAddressesCount )
        ELASTIC_APM_WRITE_FROM_SIGNAL_HANDLER( "    Call stack frame #%d/%d address: %p\n", (int)(i + 1), stackTraceAddressesCount, stackTraceAddresses[ i ] );

    char** stackTraceAddressesAsSymbols = backtrace_symbols( stackTraceAddresses, stackTraceAddressesCount );
    if ( stackTraceAddressesAsSymbols == NULL )
    {
        ELASTIC_APM_WRITE_FROM_SIGNAL_HANDLER( "backtrace_symbols returned NULL (i.e., failed to resolve addresses to symbols). Addresses:\n" );
        return;
    }

    ELASTIC_APM_FOR_EACH_INDEX( i, stackTraceAddressesCount )
        ELASTIC_APM_WRITE_FROM_SIGNAL_HANDLER( "    Call stack frame #%d/%d: %s\n", (int)(i + 1), stackTraceAddressesCount, stackTraceAddressesAsSymbols[ i ] );

    free( stackTraceAddressesAsSymbols );
    stackTraceAddressesAsSymbols = NULL;
}
#endif

void handleOsSignalLinux( int signalId )
{
    ELASTIC_APM_WRITE_FROM_SIGNAL_HANDLER(
            "Received signal %d (%s). %s"
            , signalId, osSignalIdToName( signalId )
            ,
#if defined( ELASTIC_APM_PLATFORM_HAS_BACKTRACE )
              "Call stack below:"
#else
              "Call stack is not supported"
#endif
        );

#if defined( ELASTIC_APM_PLATFORM_HAS_BACKTRACE )
    writeStackTraceToSyslog();
#endif

    /* Call the default signal handler to have core dump generated... */
    signal( signalId, 0 );
    raise ( signalId );
}

#undef ELASTIC_APM_WRITE_FROM_SIGNAL_HANDLER
#endif // #ifndef PHP_WIN32

void registerOsSignalHandler()
{
#ifndef PHP_WIN32
    signal( SIGSEGV, handleOsSignalLinux );
#endif
}

