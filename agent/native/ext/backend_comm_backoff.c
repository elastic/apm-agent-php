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
#include <stdlib.h>
#include <math.h>
#include "basic_macros.h"
#include "log.h"
#include "time_util.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_BACKEND_COMM

/**
 * Algorithm is based on Elastic APM agent spec's "Transport errors" section
 *
 * @see https://github.com/elastic/apm/blob/d8cb5607dbfffea819ab5efc9b0743044772fb23/specs/agents/transport.md#transport-errors
 */

void backendCommBackoff_onSuccess( BackendCommBackoff* thisObj )
{
    ELASTIC_APM_ASSERT_VALID_PTR( thisObj );

    thisObj->errorCount = 0;
    thisObj->waitEndTime = (TimeSpec){ 0 };
}

bool backendCommBackoff_getCurrentTime( BackendCommBackoff* thisObj, /* out */ TimeSpec* currentTime )
{
    ResultCode resultCode;
    ELASTIC_APM_CALL_IF_FAILED_GOTO( getClockTimeSpec( /* isRealTime */ false, /* out */ currentTime ) );

    resultCode = resultSuccess;
    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode == resultSuccess;

    failure:
    ELASTIC_APM_LOG_ERROR( "Failed to get current time" );
    goto finally;
}

void backendCommBackoff_onError( BackendCommBackoff* thisObj )
{
    ELASTIC_APM_ASSERT_VALID_PTR( thisObj );

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    /**
     *  The grace period should be calculated in seconds using the algorithm min(reconnectCount++, 6) ** 2 ± 10%
     *
     * @see https://github.com/elastic/apm/blob/d8cb5607dbfffea819ab5efc9b0743044772fb23/specs/agents/transport.md#transport-errors
     */
    enum { maxSequentialErrorsCount = 7 };

    if ( thisObj->errorCount < maxSequentialErrorsCount )
    {
        ++thisObj->errorCount;
    }

    if ( ! backendCommBackoff_getCurrentTime( thisObj, /* out */ &thisObj->waitEndTime ) )
    {
        // If we cannot get current time we just reset the state to that of no errors
        backendCommBackoff_onSuccess( thisObj );
        return;
    }
    addDelayToAbsTimeSpec( /* in, out */ &thisObj->waitEndTime, /* delayInNanoseconds */ (long)backendCommBackoff_getTimeToWaitInSeconds( thisObj ) * ELASTIC_APM_NUMBER_OF_NANOSECONDS_IN_SECOND );
}

int backendCommBackoff_convertRandomUIntToJitter( UInt randomVal, UInt jitterHalfRange )
{
    double diff = randomVal - ( RAND_MAX / 2.0 );
    return ( diff >= 0 ? 1 : -1 ) * ( (int) floor( ( jitterHalfRange * fabs( diff ) ) / ( RAND_MAX / 2.0 ) ) );
}

UInt backendCommBackoff_getTimeToWaitInSeconds( const BackendCommBackoff* thisObj )
{
    ELASTIC_APM_ASSERT_VALID_PTR( thisObj );

    if ( thisObj->errorCount == 0 )
    {
        return 0;
    }

    UInt reconnectCount = ( thisObj->errorCount - 1);
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

bool backendCommBackoff_shouldWait( BackendCommBackoff* thisObj )
{
    if ( thisObj->errorCount == 0 )
    {
        return false;
    }

    TimeSpec currentTime;
    if ( ! backendCommBackoff_getCurrentTime( thisObj, /* out */ &currentTime ) )
    {
        // If we cannot get current time we just reset the state to that of no errors
        backendCommBackoff_onSuccess( thisObj );
        return false;
    }

    if ( compareAbsTimeSpecs( &thisObj->waitEndTime, &currentTime ) <= 0 )
    {
        return false;
    }

    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    ELASTIC_APM_LOG_TRACE( "Left to wait: %s, errorCount: %u", streamTimeSpecDiff( &currentTime, &thisObj->waitEndTime, &txtOutStream ), thisObj->errorCount );
    return true;
}
