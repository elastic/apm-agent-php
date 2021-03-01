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

#include <errno.h>
#include <string.h>
#include "platform.h"
#include "unit_test_util.h"
#include "TextOutputStream_tests.h"

static
void stream_errno( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    memset( txtOutStreamBuf, 'x', ELASTIC_APM_STATIC_ARRAY_SIZE( txtOutStreamBuf ) );

    const String EACCES_str = streamErrNo( EACCES, &txtOutStream );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( EACCES_str, "denied" );
    const String EACCES_str2 = streamErrNo( EACCES, &txtOutStream );
    assert_string_equal( EACCES_str, EACCES_str2 );

    const String EBADF_str = streamErrNo( EBADF, &txtOutStream );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( EBADF_str, "Bad" );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( EBADF_str, "file" );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( EBADF_str, "descriptor" );

    textOutputStreamRewind( &txtOutStream );
    memset( txtOutStreamBuf, 'x', ELASTIC_APM_STATIC_ARRAY_SIZE( txtOutStreamBuf ) );

    const String ENOENT_str = streamErrNo( ENOENT, &txtOutStream );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( ENOENT_str, "No" );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( ENOENT_str, "file" );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( ENOENT_str, "directory" );

    const String non_existing_errno_str = streamErrNo( 999, &txtOutStream );
    #ifdef PHP_WIN32
    ELASTIC_APM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( non_existing_errno_str, "Unknown" );
    #else
    #   ifdef __GLIBC__
    ELASTIC_APM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( non_existing_errno_str, "Unknown" );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( non_existing_errno_str, "999" );
    #   else
    ELASTIC_APM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( non_existing_errno_str, "No error information" );
    #   endif
    #endif
}

static
String streamErrNoUnderTest( TextOutputStream* txtOutStream )
{
    return streamErrNo( ENOENT, txtOutStream );
}

static
void stream_errno_overflow( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    testStreamXyzOverflow( streamErrNoUnderTest );
}

#ifndef ELASTIC_APM_CAN_CAPTURE_STACK_TRACE_01
#   ifdef ELASTIC_APM_PLATFORM_HAS_BACKTRACE
#       define ELASTIC_APM_CAN_CAPTURE_STACK_TRACE_01 1
#   else // #ifdef ELASTIC_APM_PLATFORM_HAS_BACKTRACE
#       define ELASTIC_APM_CAN_CAPTURE_STACK_TRACE_01 0
#   endif // #ifdef ELASTIC_APM_PLATFORM_HAS_BACKTRACE
#endif // #ifndef ELASTIC_APM_CAN_CAPTURE_STACK_TRACE_01

#if ( ELASTIC_APM_CAN_CAPTURE_STACK_TRACE_01 != 0 )
// This function should not be static - otherwise it won't show up on the stack trace
// static
void capturing_stack_trace( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    #ifndef PHP_WIN32
    void* addressesBuffer[ maxCaptureStackTraceDepth ];
    size_t addressesCount = ELASTIC_APM_CAPTURE_STACK_TRACE( &(addressesBuffer[ 0 ]), ELASTIC_APM_STATIC_ARRAY_SIZE( addressesBuffer ) );

    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE * 10 ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    String stackTraceAsString = streamStackTrace( &(addressesBuffer[ 0 ]), addressesCount, /* linePrefix: */ "\t", &txtOutStream );

    assert_ptr_not_equal( strstr( stackTraceAsString, __FUNCTION__ ), NULL );
    #endif
}
#endif // ##if ( ELASTIC_APM_CAN_CAPTURE_STACK_TRACE_01 != 0 )

int run_platform_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( stream_errno ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( stream_errno_overflow ),
#       if ( ELASTIC_APM_CAN_CAPTURE_STACK_TRACE_01 != 0 )
        ELASTIC_APM_CMOCKA_UNIT_TEST( capturing_stack_trace ),
#       endif // ##if ( ELASTIC_APM_CAN_CAPTURE_STACK_TRACE_01 != 0 )
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
