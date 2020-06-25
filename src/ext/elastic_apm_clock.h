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

#pragma once

#include <time.h>
#include <stdbool.h>

#ifdef PHP_WIN32
#   ifdef ELASTIC_APM_MOCK_CLOCK

struct timeval
{
    long tv_sec;         /* seconds */
    long tv_usec;        /* and microseconds */
};

struct timezone
{
    int tz_minuteswest;
    int tz_dsttime;
};

#   else
#       include <win32/time.h>
#   endif
#else
#   include <sys/time.h>
#endif

#ifdef PHP_WIN32
#   include "platform.h"
#endif

static inline
bool convertUtcToLocalTimeDefaultImpl( time_t input, struct tm* output, long* secondsAheadUtc )
{
    struct tm outputLocal = { 0 };
    long secondsAheadUtcLocal = 0;

    #ifdef PHP_WIN32

    errno_t localtimeRetVal = localtime_s( &outputLocal, &input );
    if ( localtimeRetVal != 0 ) return false;
    if ( ! getTimeZoneShiftOnWindows( &secondsAheadUtcLocal ) ) return false;

    #else

    struct tm* localtimeRetVal = localtime_r( &input, &outputLocal );
    if ( localtimeRetVal == NULL ) return false;

    // https://www.gnu.org/software/libc/manual/html_node/Broken_002ddown-Time.html
    // tm_gmtoff - number of seconds that you must add to UTC to get local time
    secondsAheadUtcLocal = outputLocal.tm_gmtoff;

    #endif

    *output = outputLocal;
    *secondsAheadUtc = secondsAheadUtcLocal;
    return true;
}

#ifdef ELASTIC_APM_MOCK_CLOCK

/**
 * @return 0 for success, or -1 for failure (in which case errno is set appropriately)
 */
int getSystemClockCurrentTimeAsUtc( struct timeval* systemClockTime );

bool convertUtcToLocalTime( time_t input, struct tm* output, long* secondsAheadUtc );

#else

static inline
int getSystemClockCurrentTimeAsUtc( struct timeval* systemClockTime )
{
    return gettimeofday( systemClockTime, /* timezoneInfo: */ NULL );
}

static inline
bool convertUtcToLocalTime( time_t input, struct tm* output, long* secondsAheadUtc )
{
    return convertUtcToLocalTimeDefaultImpl( input, output, secondsAheadUtc );
}

#endif
