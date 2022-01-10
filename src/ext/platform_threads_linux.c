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

#include "platform_threads.h"
//#ifndef PHP_WIN32
//#   include <features.h>
//#endif
#include <pthread.h>
#include <errno.h>
#include "elastic_apm_assert.h"
#include "elastic_apm_alloc.h"
#include "log.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_PLATFORM

struct Thread
{
    pthread_t thread;
    const char* dbgDesc;
};

ResultCode newThread( Thread** threadOutPtr
                      , void* (* threadFunc )( void* )
                      , void* threadFuncArg
                      , const char* dbgDesc )
{
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( threadOutPtr );

    ResultCode resultCode;
    Thread* thread = NULL;
    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_MALLOC_INSTANCE_IF_FAILED_GOTO( Thread, /* out */ thread );
    pthreadResultCode = pthread_create( &(thread->thread), /* attr: */ NULL, threadFunc, threadFuncArg );
    if ( pthreadResultCode != 0 )
    {
        ELASTIC_APM_LOG_ERROR(
                "pthread_create failed with error: `%s'; dbg desc: `%s'"
                , streamErrNo( pthreadResultCode, &txtOutStream ), dbgDesc );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    thread->dbgDesc = dbgDesc;
    resultCode = resultSuccess;
    *threadOutPtr = thread;

    finally:
    return resultCode;

    failure:
    ELASTIC_APM_FREE_INSTANCE_AND_SET_TO_NULL( Thread, thread );
    goto finally;
}

ResultCode timedJoinAndDeleteThread( Thread** threadOutPtr, void** threadFuncRetVal, const TimeSpec* timeoutAbsUtc, /* out */ bool* hasTimedOut, const char* dbgDesc )
{
    ELASTIC_APM_ASSERT_VALID_IN_PTR_TO_PTR( threadOutPtr );
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( threadFuncRetVal );
    // ELASTIC_APM_ASSERT_VALID_PTR( timeoutAbsUtc ); <- timeoutAbsUtc can be NULL
    ELASTIC_APM_ASSERT_VALID_PTR( hasTimedOut );

    ResultCode resultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    const char* dbgFuncDescSuffix = ( timeoutAbsUtc == NULL ? "" : " with timeout" );
    const char* dbgTimeoutAsLocal = ( timeoutAbsUtc == NULL ? "NULL" : streamUtcTimeSpecAsLocal( timeoutAbsUtc, &txtOutStream ) );
    const char* dbgPThreadsFuncDesc = ( timeoutAbsUtc == NULL ? "pthread_join" : "pthread_timedjoin_np" );

    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY_MSG(
            "Join and delete thread%s"
            "; timeoutAbsUtc: %s; thread dbg desc: `%s'; call dbg desc: `%s'"
            , dbgFuncDescSuffix
            , dbgTimeoutAsLocal, ( *threadOutPtr )->dbgDesc, dbgDesc );

//    int pthreadResultCode = ( timeoutAbsUtc == NULL
//                              ? pthread_join( (*threadOutPtr)->thread, threadFuncRetVal )
//                              : pthread_timedjoin_np( (*threadOutPtr)->thread, threadFuncRetVal, timeoutAbsUtc ) );
    int pthreadResultCode = pthread_join( (*threadOutPtr)->thread, threadFuncRetVal );

    if ( pthreadResultCode == 0 )
    {
        *hasTimedOut = false;
    }
    else if ( pthreadResultCode == ETIMEDOUT )
    {
        *hasTimedOut = true;
    }
    else
    {
        ELASTIC_APM_LOG_ERROR(
                "%s failed with error: `%s'"
                "; timeoutAbsUtc: %s; thread dbg desc: `%s'; call dbg desc: `%s'"
                , dbgPThreadsFuncDesc , streamErrNo( pthreadResultCode, &txtOutStream )
                , dbgTimeoutAsLocal, (*threadOutPtr)->dbgDesc, dbgDesc );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    resultCode = resultSuccess;
    ELASTIC_APM_FREE_INSTANCE_AND_SET_TO_NULL( Thread, *threadOutPtr );

    finally:
    ELASTIC_APM_LOG_TRACE_FUNCTION_EXIT_RESULT_CODE_MSG(
            "Join and delete thread%s"
            "; hasTimedOut: %s"
            "; timeoutAbsUtc: %s; call dbg desc: `%s'"
            , dbgFuncDescSuffix
            , resultCode == resultSuccess ? boolToString( *hasTimedOut ) : "N/A"
            , dbgTimeoutAsLocal, dbgDesc );
    return resultCode;

    failure:
    goto finally;
}

UInt64 getThreadId( Thread* thread )
{
    ELASTIC_APM_ASSERT_VALID_PTR( thread );

    return (UInt) thread->thread;
}

struct Mutex
{
    pthread_mutex_t mutex;
    const char* dbgDesc;
};

ResultCode newMutex( Mutex** mtxOutPtr, const char* dbgDesc )
{
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( mtxOutPtr );

    ResultCode resultCode;
    Mutex* mtx = NULL;
    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_MALLOC_INSTANCE_IF_FAILED_GOTO( Mutex, /* out */ mtx );
    pthreadResultCode = pthread_mutex_init( &(mtx->mutex), /* attr: */ NULL );
    if ( pthreadResultCode != 0 )
    {
        ELASTIC_APM_LOG_ERROR(
                "pthread_mutex_init failed with error: `%s'; dbg desc: `%s'"
                , streamErrNo( pthreadResultCode, &txtOutStream ), dbgDesc );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    mtx->dbgDesc = dbgDesc;
    resultCode = resultSuccess;
    *mtxOutPtr = mtx;

    finally:
    return resultCode;

    failure:
    ELASTIC_APM_FREE_INSTANCE_AND_SET_TO_NULL( Mutex, mtx );
    goto finally;
}

ResultCode deleteMutex( Mutex** mtxOutPtr )
{
    ELASTIC_APM_ASSERT_VALID_IN_PTR_TO_PTR( mtxOutPtr );
    Mutex* mtx = *mtxOutPtr;

    ResultCode resultCode;
    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    pthreadResultCode = pthread_mutex_destroy( &( mtx->mutex ) );
    if ( pthreadResultCode != 0 )
    {
        ELASTIC_APM_LOG_ERROR(
                "pthread_mutex_destroy failed with error: `%s'; dbg desc: `%s'"
                , streamErrNo( pthreadResultCode, &txtOutStream ), mtx->dbgDesc );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    resultCode = resultSuccess;
    ELASTIC_APM_FREE_INSTANCE_AND_SET_TO_NULL( Mutex, *mtxOutPtr );
    mtx = NULL;

    finally:
    return resultCode;

    failure:
    goto finally;
}

ResultCode lockMutex( Mutex* mtx, /* out */ bool* shouldUnlock, const char* dbgDesc )
{
    ELASTIC_APM_ASSERT_VALID_PTR( mtx );
    ELASTIC_APM_ASSERT_VALID_PTR( shouldUnlock );
    ELASTIC_APM_ASSERT( ! *shouldUnlock, "" );

    *shouldUnlock = false;

    if ( ! isInLogContext() )
    {
        ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY_MSG( "Locking mutex... mutex address: %p, dbg desc: `%s'; call dbg desc: `%s'", mtx, mtx->dbgDesc, dbgDesc );
    }

    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    pthreadResultCode = pthread_mutex_lock( &(mtx->mutex) );
    if ( pthreadResultCode != 0 )
    {
        ELASTIC_APM_LOG_ERROR(
                "pthread_mutex_lock failed with error: `%s'; mutex address: %p, dbg desc: `%s'; call dbg desc: `%s'"
                , streamErrNo( pthreadResultCode, &txtOutStream ), mtx, mtx->dbgDesc, dbgDesc );
        return resultFailure;
    }

    if ( ! isInLogContext() )
    {
        ELASTIC_APM_LOG_TRACE( "Locked mutex. mutex address: %p, dbg desc: `%s'; call dbg desc: `%s'", mtx, mtx->dbgDesc, dbgDesc );
    }
    *shouldUnlock = true;
    return resultSuccess;
}

ResultCode unlockMutex( Mutex* mtx, /* in,out */ bool* shouldUnlock, const char* dbgDesc )
{
    ELASTIC_APM_ASSERT_VALID_PTR( mtx );
    ELASTIC_APM_ASSERT_VALID_PTR( shouldUnlock );

    if ( ! *shouldUnlock )
    {
        return resultSuccess;
    }

    if ( ! isInLogContext() )
    {
        ELASTIC_APM_LOG_TRACE( "Unlocking mutex... mutex address: %p, dbg desc: `%s'; call dbg desc: `%s'", mtx, mtx->dbgDesc, dbgDesc );
    }

    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    pthreadResultCode = pthread_mutex_unlock( &(mtx->mutex) );
    if ( pthreadResultCode != 0 )
    {
        ELASTIC_APM_LOG_ERROR(
                "pthread_mutex_unlock failed with error: `%s'; mutex address: %p, dbg desc: `%s'; call dbg desc: `%s'"
                , streamErrNo( pthreadResultCode, &txtOutStream ), mtx, mtx->dbgDesc, dbgDesc );
        return resultFailure;
    }


    if ( ! isInLogContext() )
    {
        ELASTIC_APM_LOG_TRACE( "Unlocked mutex. mutex address: %p, dbg desc: `%s'; call dbg desc: `%s'", mtx, mtx->dbgDesc, dbgDesc );
    }
    *shouldUnlock = false;
    return resultSuccess;
}


struct ConditionVariable
{
    pthread_cond_t conditionVariable;
    const char* dbgDesc;
};

ResultCode newConditionVariable( ConditionVariable** condVarOutPtr, const char* dbgDesc )
{
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( condVarOutPtr );

    ResultCode resultCode;
    ConditionVariable* condVar = NULL;
    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_MALLOC_INSTANCE_IF_FAILED_GOTO( ConditionVariable, /* out */ condVar );
    pthreadResultCode = pthread_cond_init( &(condVar->conditionVariable), /* attr: */ NULL );
    if ( pthreadResultCode != 0 )
    {
        ELASTIC_APM_LOG_ERROR(
                "pthread_cond_init failed with error: `%s'; dbg desc: `%s'"
                , streamErrNo( pthreadResultCode, &txtOutStream ), dbgDesc );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    condVar->dbgDesc = dbgDesc;
    resultCode = resultSuccess;
    *condVarOutPtr = condVar;

    finally:
    return resultCode;

    failure:
    ELASTIC_APM_FREE_INSTANCE_AND_SET_TO_NULL( ConditionVariable, condVar );
    goto finally;
}

ResultCode deleteConditionVariable( ConditionVariable** condVarOutPtr )
{
    ELASTIC_APM_ASSERT_VALID_IN_PTR_TO_PTR( condVarOutPtr );

    ResultCode resultCode;
    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    pthreadResultCode = pthread_cond_destroy( &((*condVarOutPtr)->conditionVariable) );
    if ( pthreadResultCode != 0 )
    {
        ELASTIC_APM_LOG_ERROR(
                "pthread_cond_destroy failed with error: `%s'; dbg desc: `%s'"
                , streamErrNo( pthreadResultCode, &txtOutStream ), (*condVarOutPtr)->dbgDesc );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    resultCode = resultSuccess;
    ELASTIC_APM_FREE_INSTANCE_AND_SET_TO_NULL( ConditionVariable, *condVarOutPtr );

    finally:
    return resultCode;

    failure:
    goto finally;
}

ResultCode waitConditionVariable( ConditionVariable* condVar, Mutex* mtx, const char* dbgDesc )
{
    ELASTIC_APM_ASSERT_VALID_PTR( condVar );
    ELASTIC_APM_ASSERT_VALID_PTR( mtx );

    ELASTIC_APM_LOG_TRACE( "Waiting condition variable... condition variable address: %p, dbg desc: `%s'; call dbg desc: `%s'", condVar, condVar->dbgDesc, dbgDesc );

    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    pthreadResultCode = pthread_cond_wait( &( condVar->conditionVariable ), &( mtx->mutex ) );
    if ( pthreadResultCode != 0 )
    {
        ELASTIC_APM_LOG_ERROR(
                "pthread_cond_wait failed with error: `%s'; condition variable address: %p, dbg desc: `%s'; mtx dbg desc: `%s'; call dbg desc: `%s'"
                , streamErrNo( pthreadResultCode, &txtOutStream ), condVar, condVar->dbgDesc, mtx->dbgDesc, dbgDesc );
        return resultFailure;
    }

    ELASTIC_APM_LOG_TRACE( "Done waiting condition variable. condition variable address: %p, dbg desc: `%s'; call dbg desc: `%s'", condVar, condVar->dbgDesc, dbgDesc );
    return resultSuccess;
}

ResultCode timedWaitConditionVariable( ConditionVariable* condVar
                                       , Mutex* mtx
                                       , const TimeSpec* timeoutAbsUtc
                                       , /* out */ bool* hasTimedOut
                                       , const char* dbgDesc )
{
    ELASTIC_APM_ASSERT_VALID_PTR( condVar );
    ELASTIC_APM_ASSERT_VALID_PTR( mtx );
    ELASTIC_APM_ASSERT_VALID_PTR( hasTimedOut );

    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY_MSG(
            "Wait on condition variable with timeout"
            "; timeoutAbsUtc: %s; condition variable address: %p, dbg desc: `%s'; call dbg desc: `%s'"
            , streamUtcTimeSpecAsLocal( timeoutAbsUtc, &txtOutStream ), condVar, condVar->dbgDesc, dbgDesc );

    int pthreadResultCode;

    pthreadResultCode = pthread_cond_timedwait( &( condVar->conditionVariable ), &( mtx->mutex ), timeoutAbsUtc );
    if ( pthreadResultCode == 0 )
    {
        *hasTimedOut = false;
    }
    else if ( pthreadResultCode == ETIMEDOUT )
    {
        *hasTimedOut = true;
    }
    else
    {
        ELASTIC_APM_LOG_ERROR(
                "pthread_cond_timedwait failed with error: `%s'"
                "; timeoutAbsUtc: %s; condition variable address: %p, dbg desc: `%s'; call dbg desc: `%s'"
                , streamErrNo( pthreadResultCode, &txtOutStream )
                , streamUtcTimeSpecAsLocal( timeoutAbsUtc, &txtOutStream ), condVar, condVar->dbgDesc, dbgDesc );
        return resultFailure;
    }

    ELASTIC_APM_LOG_TRACE_FUNCTION_EXIT_MSG(
            "Wait on condition variable with timeout"
            "; hasTimedOut: %s"
            "; timeoutAbsUtc: %s; condition variable address: %p, dbg desc: `%s'; call dbg desc: `%s'"
            , boolToString( *hasTimedOut )
            , streamUtcTimeSpecAsLocal( timeoutAbsUtc, &txtOutStream ), condVar, condVar->dbgDesc, dbgDesc );
    return resultSuccess;
}

ResultCode signalConditionVariable( ConditionVariable* condVar, const char* dbgDesc )
{
    ELASTIC_APM_ASSERT_VALID_PTR( condVar );

    ELASTIC_APM_LOG_TRACE( "Signaling condition variable... condition variable address: %p, dbg desc: `%s'; call dbg desc: `%s'", condVar, condVar->dbgDesc, dbgDesc );

    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    pthreadResultCode = pthread_cond_signal( &( condVar->conditionVariable ) );
    if ( pthreadResultCode != 0 )
    {
        ELASTIC_APM_LOG_ERROR(
                "pthread_cond_signal failed with error: `%s'; condition variable address: %p, dbg desc: `%s'; call dbg desc: `%s'"
                , streamErrNo( pthreadResultCode, &txtOutStream ), condVar, condVar->dbgDesc, dbgDesc );
        return resultFailure;
    }

    ELASTIC_APM_LOG_TRACE( "Signaled condition variable... condition variable address: %p, dbg desc: `%s'; call dbg desc: `%s'", condVar, condVar->dbgDesc, dbgDesc );

    return resultSuccess;
}
