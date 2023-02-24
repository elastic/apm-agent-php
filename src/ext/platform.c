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
#include <stdlib.h>
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
#   include <errno.h>
#endif

#if defined( ELASTIC_APM_PLATFORM_HAS_LIBUNWIND )
#   define UNW_LOCAL_ONLY
#   include <libunwind.h>
#endif // if defined( ELASTIC_APM_PLATFORM_HAS_LIBUNWIND )

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

pid_t getParentProcessId()
{
    #ifdef PHP_WIN32

    return -1;

    #else

    return getppid();

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

#ifdef ELASTIC_APM_PLATFORM_HAS_LIBUNWIND
void iterateOverCStackTraceLibUnwind( size_t numberOfFramesToSkip, IterateOverCStackTraceCallback callback, IterateOverCStackTraceLogErrorCallback logErrorCallback, void* callbackCtx )
{
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

#   define ELASTIC_APM_LIBUNWIND_CALL_RETURN_ON_ERROR( expr ) \
        do { \
            int temp_libUnwindRetVal = (expr); \
            if ( temp_libUnwindRetVal < 0 ) \
            { \
                textOutputStreamRewind( &txtOutStream ); \
                logErrorCallback( streamPrintf( &txtOutStream, "%s call failed (return value: %d)", ELASTIC_APM_PP_STRINGIZE( expr ), temp_libUnwindRetVal ), callbackCtx ); \
                return; \
            } \
        } while ( 0 )

    unw_cursor_t unwindCursor;
    unw_context_t unwindContext;
    enum { funcNameBufferSize = 100 };
    char funcNameBuffer[ funcNameBufferSize ];
    unw_word_t offsetInsideFunc;
    size_t frameIndex = 0;

    ELASTIC_APM_LIBUNWIND_CALL_RETURN_ON_ERROR( unw_getcontext( &unwindContext ) );
    ELASTIC_APM_LIBUNWIND_CALL_RETURN_ON_ERROR( unw_init_local( &unwindCursor, &unwindContext ) );

    for (;; ++frameIndex)
    {
        // +1 is for this function frame
        if ( frameIndex >= numberOfFramesToSkip + 1 )
        {
            // https://www.nongnu.org/libunwind/man/unw_get_proc_name(3).html
            int getProcNameRetVal = unw_get_proc_name( &unwindCursor, funcNameBuffer, funcNameBufferSize, &offsetInsideFunc );
            textOutputStreamRewind( &txtOutStream );
            switch ( getProcNameRetVal ) // https://www.nongnu.org/libunwind/man/unw_get_proc_name(3).html
            {
                case 0: // On successful completion, unw_get_proc_name() returns 0
                    callback( streamPrintf( &txtOutStream, "%s + 0x%X", funcNameBuffer, (unsigned int)offsetInsideFunc ), callbackCtx );
                    break;
                case UNW_EUNSPEC: // An unspecified error occurred.
                    logErrorCallback( streamPrintf( &txtOutStream, "unw_get_proc_name call failed with return value UNW_EUNSPEC - stopping iteration over call stack" ), callbackCtx );
                    return;
                case UNW_ENOINFO: // Libunwind was unable to determine the name of the procedure.
                    callback( NULL, callbackCtx );
                    break;
                case UNW_ENOMEM: // The procedure name is too long to fit in the buffer provided. A truncated version of the name has been returned.
                    callback( streamPrintf( &txtOutStream, "%s (truncated) + 0x%X", funcNameBuffer, (unsigned int)offsetInsideFunc ), callbackCtx );
                    break;
                default:
                    logErrorCallback( streamPrintf( &txtOutStream, "unw_get_proc_name call failed with an unexpected return value (%d) - stopping iteration over call stack", getProcNameRetVal ), callbackCtx );
                    return;
            }
        }
        int unwindStepRetVal = 0;
        ELASTIC_APM_LIBUNWIND_CALL_RETURN_ON_ERROR( unwindStepRetVal = unw_step( &unwindCursor ) );
        if ( unwindStepRetVal == 0 )
        {
            break;
        }
    }

#   undef ELASTIC_APM_LIBUNWIND_CALL_RETURN_ON_ERROR
}
#endif // #ifdef ELASTIC_APM_PLATFORM_HAS_LIBUNWIND

#ifdef ELASTIC_APM_PLATFORM_HAS_BACKTRACE
void iterateOverCStackTraceBacktrace( size_t numberOfFramesToSkip, IterateOverCStackTraceCallback callback, IterateOverCStackTraceLogErrorCallback logErrorCallback, void* callbackCtx )
{
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    enum { maxStackTraceAddressesCount = 100 };
    void* stackTraceAddresses[ maxStackTraceAddressesCount ];
    int stackTraceAddressesCount = backtrace( stackTraceAddresses, maxStackTraceAddressesCount );
    if ( stackTraceAddressesCount == 0 )
    {
        textOutputStreamRewind( &txtOutStream );
        logErrorCallback( streamPrintf( &txtOutStream, "backtrace returned 0 as stackTraceAddressesCount (i.e., failed to get any address on the stack)" ), callbackCtx );
        return;
    }

    char** stackTraceAddressesAsSymbols = backtrace_symbols( stackTraceAddresses, stackTraceAddressesCount );
    if ( stackTraceAddressesAsSymbols == NULL )
    {
        textOutputStreamRewind( &txtOutStream );
        logErrorCallback( streamPrintf( &txtOutStream, "backtrace_symbols returned NULL (i.e., failed to resolve addresses to symbols). Returning raw addresses as hex strings" ), callbackCtx );
        ELASTIC_APM_FOR_EACH_INDEX( frameIndex, stackTraceAddressesCount )
        {
            // +1 is for this function frame
            if ( frameIndex < numberOfFramesToSkip + 1 )
            {
                continue;
            }
            textOutputStreamRewind( &txtOutStream );
            callback( streamPrintf( &txtOutStream, "%p", stackTraceAddresses[ frameIndex ] ), callbackCtx );
        }
        return;
    }

    ELASTIC_APM_FOR_EACH_INDEX( frameIndex, stackTraceAddressesCount )
    {
        // +1 is for this function frame
        if ( frameIndex < numberOfFramesToSkip + 1 )
        {
            continue;
        }
        textOutputStreamRewind( &txtOutStream );
        callback( streamPrintf( &txtOutStream, "%s", stackTraceAddressesAsSymbols[ frameIndex ] ), callbackCtx );
    }

    free( stackTraceAddressesAsSymbols );
    stackTraceAddressesAsSymbols = NULL;
}
#endif // #ifdef ELASTIC_APM_PLATFORM_HAS_BACKTRACE

#ifdef ELASTIC_APM_CAN_CAPTURE_C_STACK_TRACE
void iterateOverCStackTrace( size_t numberOfFramesToSkip, IterateOverCStackTraceCallback callback, IterateOverCStackTraceLogErrorCallback logErrorCallback, void* callbackCtx )
{
#   if defined( ELASTIC_APM_PLATFORM_HAS_LIBUNWIND )

    iterateOverCStackTraceLibUnwind( numberOfFramesToSkip + 1, callback, logErrorCallback, callbackCtx );

#   elif defined( ELASTIC_APM_PLATFORM_HAS_BACKTRACE )

    iterateOverCStackTraceBacktrace( numberOfFramesToSkip + 1, callback, logErrorCallback, callbackCtx );

#   endif
}
#endif // #ifdef ELASTIC_APM_CAN_CAPTURE_C_STACK_TRACE

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

#define ELASTIC_APM_LOG_FROM_CRASH_SIGNAL_HANDLER( fmt, ... ) ELASTIC_APM_SIGNAL_SAFE_LOG_CRITICAL( fmt, ##__VA_ARGS__ )

#ifdef ELASTIC_APM_CAN_CAPTURE_C_STACK_TRACE
void handleOsSignalLinux_writeStackTraceFrameToSyslog( String frameDesc, void* ctx )
{
    ELASTIC_APM_UNUSED( ctx );
    ELASTIC_APM_LOG_FROM_CRASH_SIGNAL_HANDLER( "    Call stack frame: %s", frameDesc == NULL ? "<N/A>" : frameDesc );
}

void handleOsSignalLinux_writeStackTraceToSyslog_logError( String errorDesc, void* ctx )
{
    ELASTIC_APM_UNUSED( ctx );
    ELASTIC_APM_LOG_FROM_CRASH_SIGNAL_HANDLER( "%s", errorDesc );
}
#endif // #ifdef ELASTIC_APM_CAN_CAPTURE_C_STACK_TRACE

void handleOsSignalLinux_writeStackTraceToSyslog()
{
#   ifdef ELASTIC_APM_CAN_CAPTURE_C_STACK_TRACE

    ELASTIC_APM_LOG_FROM_CRASH_SIGNAL_HANDLER( "Call stack:" );
    iterateOverCStackTrace( /* numberOfFramesToSkip */ 0, &handleOsSignalLinux_writeStackTraceFrameToSyslog, &handleOsSignalLinux_writeStackTraceToSyslog_logError, /* callbackCtx */ NULL );

#   else // #ifdef ELASTIC_APM_CAN_CAPTURE_C_STACK_TRACE
    ELASTIC_APM_LOG_FROM_CRASH_SIGNAL_HANDLER( "C call stack capture is not supported by the platform");
#   endif // #ifdef ELASTIC_APM_CAN_CAPTURE_C_STACK_TRACE
}

typedef void (* OsSignalHandler )( int );
bool isOldSignalHandlerSet = false;
OsSignalHandler oldSignalHandler = NULL;

void handleOsSignalLinux( int signalId )
{
    ELASTIC_APM_LOG_FROM_CRASH_SIGNAL_HANDLER( "Received signal %d (%s)", signalId, osSignalIdToName( signalId ) );
    handleOsSignalLinux_writeStackTraceToSyslog();

    /* Call the default signal handler to have core dump generated... */
    if ( isOldSignalHandlerSet )
    {
        signal( signalId, oldSignalHandler );
        isOldSignalHandlerSet = false;
        oldSignalHandler = NULL;
    }
    else
    {
        signal( signalId, SIG_DFL );
    }
    raise ( signalId );
}
#endif // #ifndef PHP_WIN32

void registerOsSignalHandler()
{
#ifndef PHP_WIN32
    OsSignalHandler signal_retVal = signal( SIGSEGV, handleOsSignalLinux );
    if ( signal_retVal == SIG_ERR )
    {
        int signal_errno = errno;

        char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        ELASTIC_APM_LOG_FROM_CRASH_SIGNAL_HANDLER( "Call to signal() to register handler failed - errno: %s", streamErrNo( signal_errno, &txtOutStream ) );
    }
    else
    {
        isOldSignalHandlerSet = true;
        oldSignalHandler = signal_retVal;
        ELASTIC_APM_SIGNAL_SAFE_LOG_DEBUG( "Successfully registered signal handler" );
    }
#endif
}

void atExitLogging()
{
    ELASTIC_APM_LOG_DIRECT_DEBUG( "Callback registered with atexit() has been called" );
}

void registerAtExitLogging()
{
#ifndef PHP_WIN32
    int atexit_retVal = atexit( &atExitLogging );
    // atexit returns 0 if successful, or a nonzero value if an error occurs
    if ( atexit_retVal != 0 )
    {
        ELASTIC_APM_LOG_DIRECT_DEBUG( "Call to atexit() to register process on-exit logging func failed" );
    }
    else
    {
        ELASTIC_APM_LOG_DIRECT_DEBUG( "Registered callback with atexit()" );
    }
#endif
}
