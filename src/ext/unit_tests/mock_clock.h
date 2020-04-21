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
#include "basic_types.h"

void setMockCurrentTime(
        UInt16 years,
        UInt8 months,
        UInt8 days,
        UInt8 hours,
        UInt8 minutes,
        UInt8 seconds,
        UInt32 microseconds,
        long secondsAheadUtc );

void revertToRealCurrentTime();
