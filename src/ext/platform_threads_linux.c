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
#include <pthread.h>
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
                "pthread_create failed with error: `%s'; dbgDesc: %s"
                , streamErrNo( pthreadResultCode, &txtOutStream ), dbgDesc );
        resultCode = resultFailure;
        goto failure;
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

void joinAndDeleteThread( Thread** threadOutPtr, void** threadFuncRetVal, const char* dbgDesc )
{
    ELASTIC_APM_ASSERT_VALID_IN_PTR_TO_PTR( threadOutPtr );
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( threadFuncRetVal );

    ELASTIC_APM_LOG_TRACE( "Joining thread... thread dbg desc %s; call dbg desc: %s", (*threadOutPtr)->dbgDesc, dbgDesc );

    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    pthreadResultCode = pthread_join( (*threadOutPtr)->thread, threadFuncRetVal );
    if ( pthreadResultCode != 0 )
    {
        ELASTIC_APM_LOG_ERROR(
                "pthread_join failed with error: `%s'; dbgDesc: %s"
                , streamErrNo( pthreadResultCode, &txtOutStream ), (*threadOutPtr)->dbgDesc );
    }

    ELASTIC_APM_FREE_INSTANCE_AND_SET_TO_NULL( Thread, *threadOutPtr );
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
                "pthread_mutex_init failed with error: `%s'; dbgDesc: %s"
                , streamErrNo( pthreadResultCode, &txtOutStream ), dbgDesc );
        resultCode = resultFailure;
        goto failure;
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

void deleteMutex( Mutex** mtxOutPtr )
{
    ELASTIC_APM_ASSERT_VALID_IN_PTR_TO_PTR( mtxOutPtr );

    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    pthreadResultCode = pthread_mutex_destroy( &((*mtxOutPtr)->mutex) );
    if ( pthreadResultCode != 0 )
    {
        ELASTIC_APM_LOG_ERROR(
                "pthread_mutex_destroy failed with error: `%s'; dbgDesc: %s"
                , streamErrNo( pthreadResultCode, &txtOutStream ), (*mtxOutPtr)->dbgDesc );
    }

    ELASTIC_APM_FREE_INSTANCE_AND_SET_TO_NULL( Mutex, *mtxOutPtr );
}

ResultCode lockMutex( Mutex* mtx, const char* dbgDesc )
{
    ELASTIC_APM_ASSERT_VALID_PTR( mtx );

    ELASTIC_APM_LOG_TRACE( "Locking mutex... mutex dbg desc %s; call dbg desc: %s", mtx->dbgDesc, dbgDesc );

    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    pthreadResultCode = pthread_mutex_lock( &(mtx->mutex) );
    if ( pthreadResultCode != 0 )
    {
        ELASTIC_APM_LOG_ERROR(
                "pthread_mutex_lock failed with error: `%s'; mutex dbgDesc: %s; call dbgDesc: %s"
                , streamErrNo( pthreadResultCode, &txtOutStream ), mtx->dbgDesc, dbgDesc );
        return resultFailure;
    }

    return resultSuccess;
}

ResultCode unlockMutex( Mutex* mtx, const char* dbgDesc )
{
    ELASTIC_APM_ASSERT_VALID_PTR( mtx );

    ELASTIC_APM_LOG_TRACE( "Unlocking mutex... mutex dbg desc %s; call dbg desc: %s", mtx->dbgDesc, dbgDesc );

    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    pthreadResultCode = pthread_mutex_unlock( &(mtx->mutex) );
    if ( pthreadResultCode != 0 )
    {
        ELASTIC_APM_LOG_ERROR(
                "pthread_mutex_unlock failed with error: `%s'; mutex dbgDesc: %s; call dbgDesc: %s"
                , streamErrNo( pthreadResultCode, &txtOutStream ), mtx->dbgDesc, dbgDesc );
        return resultFailure;
    }

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
                "pthread_cond_init failed with error: `%s'; dbgDesc: %s"
                , streamErrNo( pthreadResultCode, &txtOutStream ), dbgDesc );
        resultCode = resultFailure;
        goto failure;
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

void deleteConditionVariable( ConditionVariable** condVarOutPtr )
{
    ELASTIC_APM_ASSERT_VALID_IN_PTR_TO_PTR( condVarOutPtr );

    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    pthreadResultCode = pthread_cond_destroy( &((*condVarOutPtr)->conditionVariable) );
    if ( pthreadResultCode != 0 )
    {
        ELASTIC_APM_LOG_ERROR(
                "pthread_cond_destroy failed with error: `%s'; dbgDesc: %s"
                , streamErrNo( pthreadResultCode, &txtOutStream ), (*condVarOutPtr)->dbgDesc );
    }

    ELASTIC_APM_FREE_INSTANCE_AND_SET_TO_NULL( ConditionVariable, *condVarOutPtr );
}

ResultCode waitConditionVariable( ConditionVariable* condVar, Mutex* mtx, const char* dbgDesc )
{
    ELASTIC_APM_ASSERT_VALID_PTR( condVar );
    ELASTIC_APM_ASSERT_VALID_PTR( mtx );

    ELASTIC_APM_LOG_TRACE( "Waiting condition variable... condition variable dbg desc %s; call dbg desc: %s", condVar->dbgDesc, dbgDesc );

    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    pthreadResultCode = pthread_cond_wait( &( condVar->conditionVariable ), &( mtx->mutex ) );
    if ( pthreadResultCode != 0 )
    {
        ELASTIC_APM_LOG_ERROR(
                "pthread_cond_wait failed with error: `%s'; condVar dbgDesc: %s; mtx dbgDesc: %s; call dbgDesc: %s"
                , streamErrNo( pthreadResultCode, &txtOutStream ), condVar->dbgDesc, mtx->dbgDesc, dbgDesc );
        return resultFailure;
    }

    return resultSuccess;
}

ResultCode signalConditionVariable( ConditionVariable* condVar, const char* dbgDesc )
{
    ELASTIC_APM_ASSERT_VALID_PTR( condVar );

    ELASTIC_APM_LOG_TRACE( "Signaling condition variable... condition variable dbg desc %s; call dbg desc: %s", condVar->dbgDesc, dbgDesc );

    int pthreadResultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    pthreadResultCode = pthread_cond_signal( &( condVar->conditionVariable ) );
    if ( pthreadResultCode != 0 )
    {
        ELASTIC_APM_LOG_ERROR(
                "pthread_cond_signal failed with error: `%s'; condVar dbgDesc: %s; call dbgDesc: %s"
                , streamErrNo( pthreadResultCode, &txtOutStream ), condVar->dbgDesc, dbgDesc );
        return resultFailure;
    }

    return resultSuccess;
}
