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

#ifndef PHP_WIN32
#   include <features.h>
#   ifdef __GLIBC__
#       define ELASTIC_APM_PLATFORM_HAS_BACKTRACE
#   endif
#endif

#include <stdbool.h>
#ifdef ELASTIC_APM_PLATFORM_HAS_BACKTRACE
#   include <execinfo.h> // backtrace
#endif
#include "basic_types.h"
#include "basic_macros.h"
#include "TextOutputStream.h"
#include "ResultCode.h"
#include "platform_threads.h"

#ifdef PHP_WIN32
typedef int pid_t;
#endif

pid_t getCurrentProcessId();

pid_t getCurrentThreadId();

#ifdef PHP_WIN32
void writeToWindowsSystemDebugger( String msg );
#endif

#ifdef PHP_WIN32
/**
 * @param  secondsAheadUtc number of seconds that you must add to UTC to get local time
 */
bool getTimeZoneShiftOnWindows( long* secondsAheadUtc );
#endif

String streamErrNo( int errnoValue, TextOutputStream* txtOutStream );

enum { maxCaptureStackTraceDepth = 100 };

#ifdef PHP_WIN32
size_t captureStackTraceWindows( void** addressesBuffer, size_t addressesBufferSize );
#define ELASTIC_APM_CAPTURE_STACK_TRACE( addressesBuffer, addressesBufferSize ) \
    captureStackTraceWindows( (addressesBuffer), (addressesBufferSize) )
#else
#   ifdef ELASTIC_APM_PLATFORM_HAS_BACKTRACE
#       define ELASTIC_APM_CAPTURE_STACK_TRACE( addressesBuffer, addressesBufferSize ) \
            backtrace( (addressesBuffer), (addressesBufferSize) )
#   else
#       define ELASTIC_APM_CAPTURE_STACK_TRACE( addressesBuffer, addressesBufferSize ) \
            0
#   endif
#endif

String streamStackTrace( void* const* addresses, size_t addressesCount, String linePrefix, TextOutputStream* txtOutStream );

String streamCurrentProcessCommandLine( TextOutputStream* txtOutStream );

String streamCurrentProcessExeName( TextOutputStream* txtOutStream );

void registerOsSignalHandler();
