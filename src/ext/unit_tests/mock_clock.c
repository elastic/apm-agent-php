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

#include "mock_clock.h"

#include <time.h>

#include "unit_test_util.h"
#include "elasticapm_clock.h"


#ifdef PHP_WIN32

#ifndef WIN32_LEAN_AND_MEAN
    #define WIN32_LEAN_AND_MEAN
#endif
#ifndef VC_EXTRALEAN
    #define VC_EXTRALEAN
#endif
#include <windows.h>

static int getfilesystemtime(struct timeval *tv)
{/*{{{*/
    FILETIME ft;
    unsigned __int64 ff = 0;
    ULARGE_INTEGER fft;

    GetSystemTimeAsFileTime(&ft);

    /*
 * Do not cast a pointer to a FILETIME structure to either a
 * ULARGE_INTEGER* or __int64* value because it can cause alignment faults on 64-bit Windows.
 * via  http://technet.microsoft.com/en-us/library/ms724284(v=vs.85).aspx
 */
    fft.HighPart = ft.dwHighDateTime;
    fft.LowPart = ft.dwLowDateTime;
    ff = fft.QuadPart;

    ff /= 10Ui64; /* convert to microseconds */
    ff -= 11644473600000000Ui64; /* convert to unix epoch */

    tv->tv_sec = (long)(ff / 1000000Ui64);
    tv->tv_usec = (long)(ff % 1000000Ui64);

    return 0;
}/*}}}*/

int gettimeofday(struct timeval *time_Info, struct timezone *timezone_Info)
{/*{{{*/
    /* Get the time, if they want it */
    if (time_Info != NULL) {
        getfilesystemtime(time_Info);
    }
    /* Get the timezone, if they want it */
    if (timezone_Info != NULL) {
        _tzset();
        timezone_Info->tz_minuteswest = _timezone;
        timezone_Info->tz_dsttime = _daylight;
    }
    /* And return */
    return 0;
}/*}}}*/

#else

#include <sys/time.h>

#endif // #ifdef PHP_WIN32

static bool g_isCurrentTimeMocked = false;
static struct tm g_mockedCurrentTime = { 0 };
static long g_mockedCurrentTimeMicroseconds = 0;
static long g_mockedCurrentTimeSecondsAheadUtc = 0;

void revertToRealCurrentTime()
{
    g_isCurrentTimeMocked = false;
    ELASTICAPM_ZERO_STRUCT( &g_mockedCurrentTime );
    g_mockedCurrentTimeMicroseconds = 0;
    g_mockedCurrentTimeSecondsAheadUtc = 0;
}

void setMockCurrentTime(
        UInt16 years,
        UInt8 months,
        UInt8 days,
        UInt8 hours,
        UInt8 minutes,
        UInt8 seconds,
        UInt32 microseconds,
        long secondsAheadUtc )
{
    ELASTICAPM_ASSERT_GE_UINT64( years, 1900 );
    ELASTICAPM_ASSERT_IN_INCLUSIVE_RANGE_UINT64( 1, months, 12 );
    ELASTICAPM_ASSERT_IN_INCLUSIVE_RANGE_UINT64( 1, days, 31 );
    ELASTICAPM_ASSERT_IN_INCLUSIVE_RANGE_UINT64( 0, hours, 23 );
    ELASTICAPM_ASSERT_IN_INCLUSIVE_RANGE_UINT64( 0, minutes, 59 );
    // seconds after the minute - [0, 60] including leap second
    ELASTICAPM_ASSERT_IN_INCLUSIVE_RANGE_UINT64( 0, seconds, 60 );
    ELASTICAPM_ASSERT_IN_END_EXCLUDED_RANGE_UINT64( 0, microseconds, 1000*1000 );

    g_isCurrentTimeMocked = true;
    // tm_year is years since 1900
    g_mockedCurrentTime.tm_year = (UInt16)( years - 1900 );
    // tm_mon is months since January - [0, 11]
    g_mockedCurrentTime.tm_mon = months - 1;
    g_mockedCurrentTime.tm_mday = days;
    g_mockedCurrentTime.tm_hour = hours;
    g_mockedCurrentTime.tm_min = minutes;
    g_mockedCurrentTime.tm_sec = seconds;

    g_mockedCurrentTimeMicroseconds = microseconds;
    g_mockedCurrentTimeSecondsAheadUtc = secondsAheadUtc;
}

//////////////////////////////////////////////////////////////////////////////
//
// getSystemClockCurrentTimeAsUtc and convertUtcToLocalTime are declared in "elasticapm_clock.h"
// because ELASTICAPM_MOCK_CLOCK is defined in unit tests' CMakeLists.txt
//

/**
 * @return 0 for success, or -1 for failure (in which case errno is set appropriately)
 */
int getSystemClockCurrentTimeAsUtc( struct timeval* systemClockTime )
{
    if ( ! g_isCurrentTimeMocked ) return gettimeofday( systemClockTime, /* timezoneInfo: */ NULL );

    ELASTICAPM_ZERO_STRUCT( systemClockTime );
    systemClockTime->tv_usec = g_mockedCurrentTimeMicroseconds;
    return 0;
}

bool convertUtcToLocalTime( time_t input, struct tm* output, long* secondsAheadUtc )
{
    if ( ! g_isCurrentTimeMocked ) return convertUtcToLocalTimeDefaultImpl( input, output, secondsAheadUtc );

    *output = g_mockedCurrentTime;
    *secondsAheadUtc = g_mockedCurrentTimeSecondsAheadUtc;
    return true;
}

//
// getSystemClockCurrentTimeAsUtc and convertUtcToLocalTime is used in "elasticapm_clock.h"
// because ELASTICAPM_MOCK_CLOCK is defined in unit tests' CMakeLists.txt
//
//////////////////////////////////////////////////////////////////////////////
