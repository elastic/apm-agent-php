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
