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

#include <stdbool.h>
#include "basic_types.h"

enum ResultCode
{
    resultSuccess,
    resultOutOfMemory,
    resultInvalidFormat,
    resultCurlFailure,
    resultFailure
};
typedef enum ResultCode ResultCode;

static inline String resultCodeToString( ResultCode resultCode )
{
    switch ( resultCode )
    {
        case resultSuccess:
            return "resultSuccess";

        case resultOutOfMemory:
            return "resultOutOfMemory";

        case resultInvalidFormat:
            return "resultInvalidFormat";

        case resultFailure:
            return "resultFailure";

        default:
            return "UNKNOWN";
    }
}

#define ELASTIC_APM_CALL_IF_FAILED_GOTO( expr ) \
    do { \
        resultCode = (expr); \
        if ( resultCode != resultSuccess ) goto failure; \
    } while ( 0 )
