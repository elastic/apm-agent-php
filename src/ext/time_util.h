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

#include "elastic_apm_clock.h"
#include "basic_types.h"
#include "StringView.h"
#include "ResultCode.h"
#include "TextOutputStream.h"
#include "constants.h"

struct TimePoint
{
    TimeVal systemClockTime;
};

typedef struct TimePoint TimePoint;

typedef struct timespec TimeSpec;

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
    durationUnits_millisecond,
    durationUnits_second,
    durationUnits_minute,

    numberOfDurationUnits
};
typedef enum DurationUnits DurationUnits;
extern StringView durationUnitsNames[ numberOfDurationUnits ];

struct Duration
{
    Int64 valueInUnits;
    DurationUnits units;
};
typedef struct Duration Duration;

static inline
bool isValidDurationUnits( DurationUnits units )
{
    return ( durationUnits_millisecond <= units ) && ( units < numberOfDurationUnits );
}

#define ELASTIC_APM_UNKNOWN_DURATION_UNITS_AS_STRING "<UNKNOWN DurationUnits>"

static inline
String durationUnitsToString( DurationUnits durationUnits )
{
    if ( isValidDurationUnits( durationUnits ) )
    {
        return durationUnitsNames[ durationUnits ].begin;
    }
    return ELASTIC_APM_UNKNOWN_DURATION_UNITS_AS_STRING;
}

static inline
Duration makeDuration( Int64 valueInUnits, DurationUnits units )
{
    return (Duration){ .valueInUnits = valueInUnits, .units = units };
}

ResultCode parseDuration( StringView inputString, DurationUnits defaultUnits, /* out */ Duration* result );

String streamDuration( Duration duration, TextOutputStream* txtOutStream );

Int64 durationToMilliseconds( Duration duration );

ResultCode getCurrentAbsTimeSpec( /* out */ TimeSpec* currentAbsTimeSpec );

void addDelayToAbsTimeSpec( /* in, out */ TimeSpec* absTimeSpec, long delayInNanoseconds );

String streamCurrentLocalTime( TextOutputStream* txtOutStream );

String streamUtcTimeSpecAsLocal( const TimeSpec* utcTimeSpec, TextOutputStream* txtOutStream );

int compareAbsTimeSpecs( const TimeSpec* a, const TimeSpec* b );

TimeVal calcEndTimeVal( TimeVal beginTime, long seconds, long nanoSeconds );
TimeVal calcTimeValDiff( TimeVal beginTime, TimeVal endTime );
