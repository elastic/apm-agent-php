/*
   +----------------------------------------------------------------------+
   | Elastic APM agent for PHP                                            |
   +----------------------------------------------------------------------+
   | Copyright (c) 2020 Elasticsearch B.V.                                |
   +----------------------------------------------------------------------+
   | Elasticsearch B.V. licenses this file under the Apache 2.0 License.  |
   | See the LICENSE file in the project root for more information.       |
   +----------------------------------------------------------------------+
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
