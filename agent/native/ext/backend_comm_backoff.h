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

#include "basic_types.h"
#include "time_util.h"

typedef UInt (* GenerateRandomUInt )( void* ctx );

struct BackendCommBackoff
{
    GenerateRandomUInt generateRandomUInt;
    void* generateRandomUIntCtx;
    UInt errorCount;
    TimeSpec waitEndTime;
};
typedef struct BackendCommBackoff BackendCommBackoff;

void backendCommBackoff_onSuccess( BackendCommBackoff* thisObj );
void backendCommBackoff_onError( BackendCommBackoff* thisObj );
bool backendCommBackoff_shouldWait( BackendCommBackoff* thisObj );

UInt backendCommBackoff_getTimeToWaitInSeconds( const BackendCommBackoff* thisObj );
int backendCommBackoff_convertRandomUIntToJitter( UInt randomVal, UInt jitterHalfRange );
UInt backendCommBackoff_defaultGenerateRandomUInt( void* ctx );

#define ELASTIC_APM_DEFAULT_BACKEND_COMM_BACKOFF \
    ((BackendCommBackoff) \
    { \
        .generateRandomUInt = &backendCommBackoff_defaultGenerateRandomUInt, \
        .generateRandomUIntCtx = NULL, \
        .errorCount = 0, \
        .waitEndTime = { .tv_sec = 0, .tv_nsec = 0 } \
    }) \
    /**/
