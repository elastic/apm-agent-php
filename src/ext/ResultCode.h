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

#include <stdbool.h>
#include "basic_types.h"
#include "StringView.h"

enum ResultCode
{
    resultSuccess,
    resultOutOfMemory,
    resultParsingFailed,
    resultCurlFailure,
    resultSyncObjUseAfterFork,
    resultFailure,

    numberOfResultCodes
};
typedef enum ResultCode ResultCode;

extern StringView resultCodeNames[ numberOfResultCodes ];

static inline
bool isValidResultCode( ResultCode resultCode )
{
    return ( resultSuccess <= resultCode ) && ( resultCode < numberOfResultCodes );
}

#define ELASTIC_APM_UNKNOWN_RESULT_CODE_AS_STRING "<UNKNOWN ResultCode>"

static inline
String resultCodeToString( ResultCode resultCode )
{
    if ( isValidResultCode( resultCode ) )
    {
        return resultCodeNames[ resultCode ].begin;
    }
    return ELASTIC_APM_UNKNOWN_RESULT_CODE_AS_STRING;
}

#define ELASTIC_APM_CALL_IF_FAILED_GOTO( expr ) \
    do { \
        resultCode = (expr); \
        if ( resultCode != resultSuccess ) goto failure; \
    } while ( 0 )

#define ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE_EX( failureResultCode ) \
    do { \
        resultCode = (failureResultCode); \
        goto failure; \
    } while ( 0 )

#define ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE() ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE_EX( resultFailure )

#define ELASTIC_APM_CALL_EARLY_GOTO_FINALLY_WITH_SUCCESS() \
    do { \
        resultCode = resultSuccess; \
        goto finally; \
    } while ( 0 )
