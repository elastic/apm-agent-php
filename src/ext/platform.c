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

#include "platform.h"
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
#endif

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
    // So negative value in Bias means that we are to the east of UTC so it is positive for our format

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
    // TODO: Sergey Kleyman: Implement: captureStackTraceWindows
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
    // TODO: Sergey Kleyman: Implement: streamStackTraceWindows
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
