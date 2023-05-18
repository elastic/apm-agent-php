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

#   else // #ifdef ELASTIC_APM_MOCK_CLOCK
#       include <win32/time.h>
#   endif // #ifdef ELASTIC_APM_MOCK_CLOCK
#else
#   include <sys/time.h>
#endif // #ifdef PHP_WIN32

typedef struct timeval TimeVal;
typedef struct timezone TimeZone;

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
int getSystemClockCurrentTimeAsUtc( TimeVal* systemClockTime );

bool convertUtcToLocalTime( time_t input, struct tm* output, long* secondsAheadUtc );

#else

static inline
int getSystemClockCurrentTimeAsUtc( TimeVal* systemClockTime )
{
    return gettimeofday( systemClockTime, /* timezoneInfo: */ NULL );
}

static inline
bool convertUtcToLocalTime( time_t input, struct tm* output, long* secondsAheadUtc )
{
    return convertUtcToLocalTimeDefaultImpl( input, output, secondsAheadUtc );
}

#endif
