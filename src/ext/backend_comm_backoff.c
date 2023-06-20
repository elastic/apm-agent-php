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

struct BackendCommBackoffState
{
    GenerateRandomUInt genRandomUInt;
    UInt sequentialErrorsCount;
};

void backendCommBackoff_init( GenerateRandomUInt genRandomUInt, BackendCommBackoffState* thisObj )
{
    ELASTIC_APM_ASSERT_VALID_PTR( thisObj );

    thisObj->genRandomUInt = genRandomUInt;
    thisObj->sequentialErrorsCount = 0;
}

void backendCommBackoff_onSuccess( BackendCommBackoffState* thisObj )
{
    ELASTIC_APM_ASSERT_VALID_PTR( thisObj );

    thisObj->sequentialErrorsCount = 0;
}

void backendCommBackoff_onError( BackendCommBackoffState* thisObj )
{
    ELASTIC_APM_ASSERT_VALID_PTR( thisObj );

    /**
     *  The grace period should be calculated in seconds using the algorithm min(reconnectCount++, 6) ** 2 ± 10%
     */
    enum { maxSequentialErrorsCount = 6 };

    thisObj->sequentialErrorsCount = ELASTIC_APM_MIN( maxSequentialErrorsCount, thisObj->sequentialErrorsCount + 1 );
}

int backendCommBackoff_convertRandomUIntToJitter( UInt randomVal, UInt jitterHalfRange )
{
    return ( (int) floor( ( 2.0 * jitterHalfRange * randomVal ) / ( (double)( RAND_MAX ) ) ) ) - ( (int) jitterHalfRange );
}

UInt backendCommBackoff_getTimeToWaitInSeconds( const BackendCommBackoffState* thisObj )
{
    ELASTIC_APM_ASSERT_VALID_PTR( thisObj );

    if ( thisObj->sequentialErrorsCount == 0 )
    {
        return 0;
    }

    UInt reconnectCount = (thisObj->sequentialErrorsCount - 1);
    double timeToWaitWithoutJitter = pow( 2, reconnectCount );
    double jitterHalfRange = timeToWaitWithoutJitter * 0.1;
    double jitter = jitterHalfRange < 1 ? 0 : backendCommBackoff_convertRandomUIntToJitter( thisObj->genRandomUInt(), (UInt) floor( jitterHalfRange ) );

    return (int)( round( timeToWaitWithoutJitter ) ) + jitter;
}

UInt backendCommBackoff_defaultGenerateRandomUInt()
{
    return (UInt) rand();
}
