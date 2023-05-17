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

#ifndef _GNU_SOURCE
#   define _GNU_SOURCE 1
#endif // ifndef _GNU_SOURCE
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
    String dbgDesc;
};

ResultCode newThread( Thread** threadOutPtr
                      , void* (* threadFunc )( void* )
                      , void* threadFuncArg
                      , String dbgDesc )
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

ResultCode timedJoinAndDeleteThread( Thread** threadOutPtr, void** threadFuncRetVal, const TimeSpec* timeoutAbsUtc, bool isCreatedByThisProcess, /* out */ bool* hasTimedOut, String dbgDesc )
{
    ELASTIC_APM_ASSERT_VALID_IN_PTR_TO_PTR( threadOutPtr );
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( threadFuncRetVal );
    // ELASTIC_APM_ASSERT_VALID_PTR( timeoutAbsUtc ); <- timeoutAbsUtc can be NULL
    ELASTIC_APM_ASSERT_VALID_PTR( hasTimedOut );

    ResultCode resultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    String dbgFuncDescSuffix = ( timeoutAbsUtc == NULL ? "" : " with timeout" );
    String dbgTimeoutAsLocal = ( timeoutAbsUtc == NULL ? "NULL" : streamUtcTimeSpecAsLocal( timeoutAbsUtc, &txtOutStream ) );
//    String dbgPThreadsFuncDesc = ( timeoutAbsUtc == NULL ? "pthread_join" : "pthread_timedjoin_np" );
    String dbgPThreadsFuncDesc = "pthread_join";

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG(
            "Join and delete thread%s"
            "; timeoutAbsUtc: %s; thread dbg desc: `%s'; call dbg desc: `%s'"
            "; isCreatedByThisProcess: %s"
            , dbgFuncDescSuffix
            , dbgTimeoutAsLocal, ( *threadOutPtr )->dbgDesc, dbgDesc
            , boolToString( isCreatedByThisProcess ) );

    if ( isCreatedByThisProcess )
    {
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
    }

    resultCode = resultSuccess;
    ELASTIC_APM_FREE_INSTANCE_AND_SET_TO_NULL( Thread, *threadOutPtr );

    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT_MSG(
            "Join and delete thread%s"
            "; hasTimedOut: %s"
            "; timeoutAbsUtc: %s; call dbg desc: `%s'"
            "; isCreatedByThisProcess: %s"
            , dbgFuncDescSuffix
            , resultCode == resultSuccess ? boolToString( *hasTimedOut ) : "N/A"
            , dbgTimeoutAsLocal, dbgDesc
            , boolToString( isCreatedByThisProcess ) );
    return resultCode;

    failure:
    goto finally;
}

UInt64 getThreadId( Thread* thread )
{
    ELASTIC_APM_ASSERT_VALID_PTR( thread );

    return (UInt64) thread->thread;
}

struct Mutex
{
    pthread_mutex_t mutex;
    String dbgDesc;
    pid_t createdByProcess;
};

ResultCode newMutex( Mutex** mtxOutPtr, String dbgDesc )
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
    mtx->createdByProcess = getCurrentProcessId();
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

    if ( mtx->createdByProcess == getCurrentProcessId() )
    {
        pthreadResultCode = pthread_mutex_destroy( &( mtx->mutex ) );
        if ( pthreadResultCode != 0 )
        {
            ELASTIC_APM_LOG_ERROR(
                    "pthread_mutex_destroy failed with error: `%s'; dbg desc: `%s'"
                    , streamErrNo( pthreadResultCode, &txtOutStream ), mtx->dbgDesc );
            ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
        }
    }

    mtx->createdByProcess = -1;

    resultCode = resultSuccess;
    ELASTIC_APM_FREE_INSTANCE_AND_SET_TO_NULL( Mutex, *mtxOutPtr );
    mtx = NULL;

    finally:
    return resultCode;

    failure:
    goto finally;
}

ResultCode checkIfMutexCreatedByCurrentProcess( const Mutex* mtx, String dbgDesc, String dbgMtxAction )
{
    if ( mtx->createdByProcess == getCurrentProcessId() )
    {
        return resultSuccess;
    }

    ELASTIC_APM_LOG_CRITICAL( "Cannot perform action on mutex created by another thread"
                              ". mutex created by process (PID): %d, action: %s, mutex address: %p, dbg desc: `%s'; call dbg desc: `%s'"
                              , (int)(mtx->createdByProcess), dbgMtxAction, mtx, mtx->dbgDesc, dbgDesc );
    return resultSyncObjUseAfterFork;
}

ResultCode lockMutexEx( Mutex* mtx, /* out */ bool* shouldUnlock, String dbgDesc, bool shouldLog )
{
    ELASTIC_APM_ASSERT_VALID_PTR( mtx );
    ELASTIC_APM_ASSERT_VALID_PTR( shouldUnlock );
    ELASTIC_APM_ASSERT( ! *shouldUnlock, "" );

    ResultCode resultCode;

    *shouldUnlock = false;

    if ( shouldLog )
    {
        ELASTIC_APM_LOG_TRACE( "Locking mutex... mutex address: %p, dbg desc: `%s'; call dbg desc: `%s'", mtx, mtx->dbgDesc, dbgDesc );
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( checkIfMutexCreatedByCurrentProcess( mtx, dbgDesc, "lock" ) );

    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    pthreadResultCode = pthread_mutex_lock( &(mtx->mutex) );
    if ( pthreadResultCode != 0 )
    {
        ELASTIC_APM_LOG_ERROR( "pthread_mutex_lock failed with error: `%s'; mutex address: %p, dbg desc: `%s'; call dbg desc: `%s'"
                               , streamErrNo( pthreadResultCode, &txtOutStream ), mtx, mtx->dbgDesc, dbgDesc );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    // Don't log for logging mutex to avoid spamming the log
    if ( shouldLog )
    {
        ELASTIC_APM_LOG_TRACE( "Locked mutex. mutex address: %p, dbg desc: `%s'; call dbg desc: `%s'", mtx, mtx->dbgDesc, dbgDesc );
    }
    *shouldUnlock = true;
    resultCode = resultSuccess;

    finally:
    return resultCode;

    failure:
    goto finally;
}

ResultCode lockMutex( Mutex* mtx, /* out */ bool* shouldUnlock, String dbgDesc )
{
    return lockMutexEx( mtx, /* out */ shouldUnlock, dbgDesc, /* shouldLog */ true );
}

ResultCode lockMutexNoLogging( Mutex* mtx, /* out */ bool* shouldUnlock, String dbgDesc )
{
    return lockMutexEx( mtx, /* out */ shouldUnlock, dbgDesc, /* shouldLog */ false );
}

ResultCode unlockMutexEx( Mutex* mtx, /* in,out */ bool* shouldUnlock, String dbgDesc, bool shouldLog )
{
    ELASTIC_APM_ASSERT_VALID_PTR( mtx );
    ELASTIC_APM_ASSERT_VALID_PTR( shouldUnlock );

    ResultCode resultCode;

    if ( ! *shouldUnlock )
    {
        resultCode = resultSuccess;
        goto finally;
    }

    if ( shouldLog )
    {
        ELASTIC_APM_LOG_TRACE( "Unlocking mutex... mutex address: %p, dbg desc: `%s'; call dbg desc: `%s'", mtx, mtx->dbgDesc, dbgDesc );
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( checkIfMutexCreatedByCurrentProcess( mtx, dbgDesc, "unlock" ) );

    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    pthreadResultCode = pthread_mutex_unlock( &(mtx->mutex) );
    if ( pthreadResultCode != 0 )
    {
        ELASTIC_APM_LOG_ERROR( "pthread_mutex_unlock failed with error: `%s'; mutex address: %p, dbg desc: `%s'; call dbg desc: `%s'"
                               , streamErrNo( pthreadResultCode, &txtOutStream ), mtx, mtx->dbgDesc, dbgDesc );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if ( shouldLog )
    {
        ELASTIC_APM_LOG_TRACE( "Unlocked mutex. mutex address: %p, dbg desc: `%s'; call dbg desc: `%s'", mtx, mtx->dbgDesc, dbgDesc );
    }
    *shouldUnlock = false;
    resultCode = resultSuccess;

    finally:
    return resultCode;

    failure:
    goto finally;
}

ResultCode unlockMutex( Mutex* mtx, /* in,out */ bool* shouldUnlock, String dbgDesc )
{
    return unlockMutexEx( mtx, /* in,out */ shouldUnlock, dbgDesc, /* shouldLog */ true );
}

ResultCode unlockMutexNoLogging( Mutex* mtx, /* in,out */ bool* shouldUnlock, String dbgDesc )
{
    return unlockMutexEx( mtx, /* in,out */ shouldUnlock, dbgDesc, /* shouldLog */ false );
}

struct ConditionVariable
{
    pthread_cond_t conditionVariable;
    String dbgDesc;
};

ResultCode newConditionVariable( ConditionVariable** condVarOutPtr, String dbgDesc )
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

ResultCode deleteConditionVariable( ConditionVariable** condVarOutPtr, bool isCreatedByThisProcess )
{
    ELASTIC_APM_ASSERT_VALID_IN_PTR_TO_PTR( condVarOutPtr );

    ResultCode resultCode;
    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    if ( isCreatedByThisProcess )
    {
        pthreadResultCode = pthread_cond_destroy( &((*condVarOutPtr)->conditionVariable) );
        if ( pthreadResultCode != 0 )
        {
            ELASTIC_APM_LOG_ERROR(
                    "pthread_cond_destroy failed with error: `%s'; dbg desc: `%s'"
                    , streamErrNo( pthreadResultCode, &txtOutStream ), (*condVarOutPtr)->dbgDesc );
            ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
        }
    }

    resultCode = resultSuccess;
    ELASTIC_APM_FREE_INSTANCE_AND_SET_TO_NULL( ConditionVariable, *condVarOutPtr );

    finally:
    return resultCode;

    failure:
    goto finally;
}

ResultCode waitConditionVariable( ConditionVariable* condVar, Mutex* mtx, String dbgDesc )
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
                                       , String dbgDesc )
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

ResultCode signalConditionVariable( ConditionVariable* condVar, String dbgDesc )
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

static void callbackToLogForkBeforeInParent()
{
    ELASTIC_APM_SIGNAL_SAFE_LOG_DEBUG( "Before process fork (i.e., in parent context); its parent (i.e., grandparent) PID: %d", (int)getParentProcessId() );
}

static void callbackToLogForkAfterInParent()
{
    ELASTIC_APM_SIGNAL_SAFE_LOG_DEBUG( "After process fork (in parent context)" );
}

static void callbackToLogForkAfterInChild()
{
    ELASTIC_APM_SIGNAL_SAFE_LOG_DEBUG( "After process fork (in child context); parent PID: %d", (int)getParentProcessId() );
    registerCallbacksToLogFork();
}

void registerCallbacksToLogFork()
{
    int retVal = pthread_atfork( callbackToLogForkBeforeInParent, callbackToLogForkAfterInParent, callbackToLogForkAfterInChild );
    if ( retVal == 0 )
    {
        ELASTIC_APM_SIGNAL_SAFE_LOG_DEBUG( "Registered callbacks to log process fork" );
    }
    else
    {
        ELASTIC_APM_SIGNAL_SAFE_LOG_WARNING( "Failed to register callbacks to log process fork; return value: %d", retVal );
    }
}
