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

#include "ResultCode.h"
#include "platform_threads.h"
#include "elastic_apm_clock.h"
#include "time_util.h"

struct Thread;
typedef struct Thread Thread;
ResultCode newThread( Thread** threadOutPtr
                      , void* (* threadFunc )( void* )
                      , void* threadFuncArg
                      , const char* dbgDesc );
ResultCode timedJoinAndDeleteThread( Thread** threadOutPtr, void** threadFuncRetVal, const TimeSpec* timeoutAbsUtc, /* out */ bool* hasTimedOut, const char* dbgDesc );
UInt64 getThreadId( Thread* thread );

struct Mutex;
typedef struct Mutex Mutex;
ResultCode newMutex( Mutex** mtxOutPtr, const char* dbgDesc );
ResultCode lockMutex( Mutex* mtx, /* out */ bool* shouldUnlock, const char* dbgDesc );
ResultCode unlockMutex( Mutex* mtx, /* in,out */ bool* shouldUnlock, const char* dbgDesc );
ResultCode deleteMutex( Mutex** mtxOutPtr );

struct ConditionVariable;
typedef struct ConditionVariable ConditionVariable;
ResultCode newConditionVariable( ConditionVariable** condVarOutPtr, const char* dbgDesc );
ResultCode waitConditionVariable( ConditionVariable* condVar, Mutex* mtx, const char* dbgDesc );
ResultCode timedWaitConditionVariable( ConditionVariable* condVar, Mutex* mtx, const TimeSpec* timeoutAbsUtc, /* out */ bool* hasTimedOut, const char* dbgDesc );
ResultCode signalConditionVariable( ConditionVariable* condVar, const char* dbgDesc );
ResultCode deleteConditionVariable( ConditionVariable** condVarOutPtr );
