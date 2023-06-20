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

#include "backend_comm_backoff.h"
#include "basic_macros.h"
#include <stdlib.h>
#include <math.h>

/**
 * Algorithm is based on Elastic APM agent spec's "Transport errors" section
 *
 * @see https://github.com/elastic/apm/blob/d8cb5607dbfffea819ab5efc9b0743044772fb23/specs/agents/transport.md#transport-errors
 */

void backendCommBackoff_init( GenerateRandomUInt generateRandomUInt, void* generateRandomUIntCtx, BackendCommBackoff* thisObj )
{
    ELASTIC_APM_ASSERT_VALID_PTR( thisObj );
    ELASTIC_APM_ASSERT_VALID_PTR( thisObj->generateRandomUInt );

    thisObj->generateRandomUInt = generateRandomUInt;
    thisObj->generateRandomUIntCtx = generateRandomUIntCtx;
    thisObj->sequentialErrorsCount = 0;
}

void backendCommBackoff_onSuccess( BackendCommBackoff* thisObj )
{
    ELASTIC_APM_ASSERT_VALID_PTR( thisObj );

    thisObj->sequentialErrorsCount = 0;
}

void backendCommBackoff_onError( BackendCommBackoff* thisObj )
{
    ELASTIC_APM_ASSERT_VALID_PTR( thisObj );

    /**
     *  The grace period should be calculated in seconds using the algorithm min(reconnectCount++, 6) ** 2 ± 10%
     */
    enum { maxSequentialErrorsCount = 7 };

    if ( thisObj->sequentialErrorsCount < maxSequentialErrorsCount )
    {
        ++thisObj->sequentialErrorsCount;
    }
}

int backendCommBackoff_convertRandomUIntToJitter( UInt randomVal, UInt jitterHalfRange )
{
    double diff = randomVal - ( RAND_MAX / 2.0 );
    return ( diff >= 0 ? 1 : -1 ) * ( (int) floor( ( jitterHalfRange * fabs( diff ) ) / ( RAND_MAX / 2.0 ) ) );
}

UInt backendCommBackoff_getTimeToWaitInSeconds( const BackendCommBackoff* thisObj )
{
    ELASTIC_APM_ASSERT_VALID_PTR( thisObj );

    if ( thisObj->sequentialErrorsCount == 0 )
    {
        return 0;
    }

    UInt reconnectCount = (thisObj->sequentialErrorsCount - 1);
    double timeToWaitWithoutJitter = pow( reconnectCount, 2 );
    double jitterHalfRange = timeToWaitWithoutJitter * 0.1;
    UInt jitter = jitterHalfRange < 1 ? 0 : backendCommBackoff_convertRandomUIntToJitter( thisObj->generateRandomUInt( thisObj->generateRandomUIntCtx ), (UInt) floor( jitterHalfRange ) );

    return (int)( round( timeToWaitWithoutJitter ) ) + jitter;
}

#pragma clang diagnostic push
#pragma ide diagnostic ignored "UnusedParameter"
UInt backendCommBackoff_defaultGenerateRandomUInt( void* ctx )
#pragma clang diagnostic pop
{
    return (UInt) rand(); // NOLINT(cert-msc50-cpp)
}
