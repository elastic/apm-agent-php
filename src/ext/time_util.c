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

#include "time_util.h"
#include <inttypes.h> // PRIu64

#define ELASTICAPM_CURRENT_LOG_CATEGORY ELASTICAPM_CURRENT_LOG_CATEGORY_UTIL

Duration makeDuration( Int64 value, DurationUnits units )
{
    // TODO: Sergey Kleyman: Implement: makeDuration

    return (Duration){ .valueInMilliseconds = value };
}

ResultCode parseDuration( StringView valueAsString, DurationUnits defaultUnits, /* out */ Duration* result )
{
    // TODO: Sergey Kleyman: Implement: parseDuration
    result->valueInMilliseconds = 10;

    return resultSuccess;
}

String streamDuration( Duration duration, TextOutputStream* txtOutStream )
{
    // TODO: Sergey Kleyman: Implement: streamDuration by using the highest units with the whole value
    // so 5s and not 5000ms

    return streamPrintf( txtOutStream, "%"PRIu64"ms", duration.valueInMilliseconds );
}

double durationToMilliseconds( Duration duration )
{
    return (double)duration.valueInMilliseconds;
}
