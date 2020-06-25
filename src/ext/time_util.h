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

#include "elastic_apm_clock.h"
#include "basic_types.h"
#include "StringView.h"
#include "ResultCode.h"
#include "TextOutputStream.h"
#include "constants.h"

struct TimePoint
{
    struct timeval systemClockTime;
};

typedef struct TimePoint TimePoint;

static inline void getCurrentTime( TimePoint* result )
{
    getSystemClockCurrentTimeAsUtc( &( result->systemClockTime ) );
}

static inline UInt64 timePointToEpochMicroseconds( const TimePoint* timePoint )
{
    ELASTIC_APM_ASSERT_VALID_PTR( timePoint );

    return timePoint->systemClockTime.tv_sec * (UInt64) ( ELASTIC_APM_NUMBER_OF_MICROSECONDS_IN_SECOND ) + timePoint->systemClockTime.tv_usec;
}

static inline UInt64 getCurrentTimeEpochMicroseconds()
{
    TimePoint currentTime;
    getCurrentTime( &currentTime );
    return timePointToEpochMicroseconds( &currentTime );
}

static inline
Int64 durationMicroseconds( const TimePoint* start, const TimePoint* end )
{
    ELASTIC_APM_ASSERT_VALID_PTR( start );
    ELASTIC_APM_ASSERT_VALID_PTR( end );

    return timePointToEpochMicroseconds( end ) - timePointToEpochMicroseconds( start );
}

// in ms with 3 decimal points
static inline
double durationMicrosecondsToMilliseconds( Int64 durationMicros )
{
    return ( (double) durationMicros ) / ELASTIC_APM_NUMBER_OF_MICROSECONDS_IN_MILLISECOND;
}

enum DurationUnits
{
    durationUnits_milliseconds,
    durationUnits_seconds,
    durationUnits_minutes
};
typedef enum DurationUnits DurationUnits;

struct Duration
{
    Int64 valueInMilliseconds;
};
typedef struct Duration Duration;

Duration makeDuration( Int64 value, DurationUnits units );

ResultCode parseDuration( StringView valueAsString, DurationUnits defaultUnits, /* out */ Duration* result );

String streamDuration( Duration duration, TextOutputStream* txtOutStream );

double durationToMilliseconds( Duration duration );
