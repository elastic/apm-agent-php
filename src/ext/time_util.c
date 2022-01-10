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

#include "time_util.h"
#include <inttypes.h> // PRIu64
#include <errno.h>
#include <math.h>
#include "log.h"
#include "platform.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_UTIL

Duration makeDuration( Int64 value, DurationUnits units )
{
    switch (units)
    {
        case durationUnits_milliseconds:
            return (Duration){ .valueInMilliseconds = value };

        case durationUnits_seconds:
            return (Duration){ .valueInMilliseconds = value * 1000 * 1000 };

        case durationUnits_minutes:
            return (Duration){ .valueInMilliseconds = value * 1000 * 1000 * 60 };

        default:
            ELASTIC_APM_ASSERT( false, "Unknown duration units (as int): %d", units );
            return (Duration){ .valueInMilliseconds = value };
    }
}

ResultCode parseDuration( StringView valueAsString, DurationUnits defaultUnits, /* out */ Duration* result )
{
    result->valueInMilliseconds = 10;

    return resultSuccess;
}

String streamDuration( Duration duration, TextOutputStream* txtOutStream )
{
    // so 5s and not 5000ms

    return streamPrintf( txtOutStream, "%"PRIu64"ms", duration.valueInMilliseconds );
}

double durationToMilliseconds( Duration duration )
{
    return (double)duration.valueInMilliseconds;
}

#ifdef PHP_WIN32
#pragma clang diagnostic push
#pragma ide diagnostic ignored "ConstantFunctionResult"
#endif
ResultCode getCurrentAbsTimeSpec( TimeSpec* currentAbsTimeSpec )
{
    ResultCode resultCode;

#ifdef PHP_WIN32
    ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
#else // #ifdef PHP_WIN32
    int clock_gettime_retVal = clock_gettime( CLOCK_REALTIME, currentAbsTimeSpec );
    if ( clock_gettime_retVal != 0 )
    {
        int clock_gettime_errno = errno;
        char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        ELASTIC_APM_LOG_ERROR( "clock_gettime failed"
                               "; errno: %s"
                               , streamErrNo( clock_gettime_errno, &txtOutStream ) );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }
#endif // #ifdef PHP_WIN32

    resultCode = resultSuccess;

    finally:
    return resultCode;

    failure:
    goto finally;
}
#ifdef PHP_WIN32
#pragma clang diagnostic pop
#endif

void addDelayToAbsTimeSpec( /* in, out */ TimeSpec* absTimeSpec, long delayInNanoseconds )
{
    ELASTIC_APM_ASSERT_VALID_PTR( absTimeSpec );

    if ( delayInNanoseconds > ELASTIC_APM_NUMBER_OF_NANOSECONDS_IN_SECOND )
    {
        absTimeSpec->tv_sec += delayInNanoseconds / ELASTIC_APM_NUMBER_OF_NANOSECONDS_IN_SECOND;
        absTimeSpec->tv_nsec += delayInNanoseconds % ELASTIC_APM_NUMBER_OF_NANOSECONDS_IN_SECOND;
    }
    else
    {
        absTimeSpec->tv_nsec += delayInNanoseconds;
    }
}

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
    const unsigned long minutesAheadUtcAbs = lround( secondsAheadUtcAbs / 60.0 );

    timeZoneShift->isPositive = secondsAheadUtc >= 0;
    timeZoneShift->minutes = (UInt8) ( minutesAheadUtcAbs % 60 );
    timeZoneShift->hours = (UInt8) ( minutesAheadUtcAbs / 60 );
}

String streamUtcTimeValAsLocal( const TimeVal* utcTimeVal, TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR( utcTimeVal );
    ELASTIC_APM_ASSERT_VALID_PTR( txtOutStream );

    struct tm localTime_tm = { 0 };
    long secondsAheadUtc = 0;

    if ( ! convertUtcToLocalTime( utcTimeVal->tv_sec, &localTime_tm, &secondsAheadUtc ) )
    {
        return "convertUtcToLocalTime() failed";
    }

    LocalTime localTime = { 0 };
    
    // tm_year is years since 1900
    localTime.years = (UInt16) ( 1900 + localTime_tm.tm_year );
    // tm_mon is months since January - [0, 11]
    localTime.months = (UInt8) ( localTime_tm.tm_mon + 1 );
    localTime.days = (UInt8) localTime_tm.tm_mday;
    localTime.hours = (UInt8) localTime_tm.tm_hour;
    localTime.minutes = (UInt8) localTime_tm.tm_min;
    localTime.seconds = (UInt8) localTime_tm.tm_sec;
    localTime.microseconds = (UInt32) utcTimeVal->tv_usec;

    calcTimeZoneShift( secondsAheadUtc, &( localTime.timeZoneShift ) );

    return streamPrintf(
            txtOutStream
            , "%04d-%02d-%02d %02d:%02d:%02d.%06d%c%02d:%02d"
            , localTime.years
            , localTime.months
            , localTime.days
            , localTime.hours
            , localTime.minutes
            , localTime.seconds
            , localTime.microseconds
            , localTime.timeZoneShift.isPositive ? '+' : '-'
            , localTime.timeZoneShift.hours
            , localTime.timeZoneShift.minutes );
}

String streamCurrentLocalTime( TextOutputStream* txtOutStream )
{
    TimeVal currentTime_UTC_timeval = { 0 };

    if ( getSystemClockCurrentTimeAsUtc( &currentTime_UTC_timeval ) != 0 )
    {
        return "getSystemClockCurrentTimeAsUtc() failed";
    }

    return streamUtcTimeValAsLocal( &currentTime_UTC_timeval, txtOutStream );
}

String streamUtcTimeSpecAsLocal( const TimeSpec* utcTimeSpec, TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR( utcTimeSpec );
    ELASTIC_APM_ASSERT_VALID_PTR( txtOutStream );

    TimeVal utcTimeVal = { 0 };
    utcTimeVal.tv_sec = utcTimeSpec->tv_sec;
    utcTimeVal.tv_usec = utcTimeSpec->tv_nsec / 1000 /* nanoseconds to microseconds */;

    return streamUtcTimeValAsLocal( &utcTimeVal, txtOutStream );
}

int compareAbsTimeSpecs( const TimeSpec* a, const TimeSpec* b )
{
#define ELASTIC_APM_NUMCMP( a, b ) ( (a) < (b) ? -1 : ( (a) > (b) ? 1 : 0 ) )

    int secCmp = ELASTIC_APM_NUMCMP( a->tv_sec, b->tv_sec );
    if ( secCmp != 0 )
    {
        return secCmp;
    }

    return ELASTIC_APM_NUMCMP( a->tv_nsec, b->tv_nsec );

#undef ELASTIC_APM_NUMCMP
}
