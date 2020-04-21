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

#include <errno.h>
#include <string.h>
#include "platform.h"
#include "unit_test_util.h"
#include "TextOutputStream_tests.h"

static
void stream_errno( void** testFixtureState )
{
    ELASTICAPM_UNUSED( testFixtureState );

    char txtOutStreamBuf[ ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    memset( txtOutStreamBuf, 'x', ELASTICAPM_STATIC_ARRAY_SIZE( txtOutStreamBuf ) );

    const String EACCES_str = streamErrNo( EACCES, &txtOutStream );
    ELASTICAPM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( EACCES_str, "denied" );
    const String EACCES_str2 = streamErrNo( EACCES, &txtOutStream );
    assert_string_equal( EACCES_str, EACCES_str2 );

    const String EBADF_str = streamErrNo( EBADF, &txtOutStream );
    ELASTICAPM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( EBADF_str, "Bad" );
    ELASTICAPM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( EBADF_str, "file" );
    ELASTICAPM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( EBADF_str, "descriptor" );

    textOutputStreamRewind( &txtOutStream );
    memset( txtOutStreamBuf, 'x', ELASTICAPM_STATIC_ARRAY_SIZE( txtOutStreamBuf ) );

    const String ENOENT_str = streamErrNo( ENOENT, &txtOutStream );
    ELASTICAPM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( ENOENT_str, "No" );
    ELASTICAPM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( ENOENT_str, "file" );
    ELASTICAPM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( ENOENT_str, "directory" );

    const String non_existing_errno_str = streamErrNo( 999, &txtOutStream );
    ELASTICAPM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( non_existing_errno_str, "Unknown" );
    #ifndef PHP_WIN32
    ELASTICAPM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( non_existing_errno_str, "999" );
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
    ELASTICAPM_UNUSED( testFixtureState );

    testStreamXyzOverflow( streamErrNoUnderTest );
}

// This function should not be static - otherwise it won't show up on the stack trace
// static
void capturing_stack_trace( void** testFixtureState )
{
    ELASTICAPM_UNUSED( testFixtureState );

    // printf( "stackTraceAsString:\n%s", stackTraceAsString );
    // TODO: Sergey Kleyman: Implement: captureStackTraceWindows
    #ifndef PHP_WIN32
    void* addressesBuffer[ maxCaptureStackTraceDepth ];
    size_t addressesCount = ELASTICAPM_CAPTURE_STACK_TRACE( &(addressesBuffer[ 0 ]), ELASTICAPM_STATIC_ARRAY_SIZE( addressesBuffer ) );

    char txtOutStreamBuf[ ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE * 10 ];
    TextOutputStream txtOutStream = ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    String stackTraceAsString = streamStackTrace( &(addressesBuffer[ 0 ]), addressesCount, /* linePrefix: */ "\t", &txtOutStream );

    assert_ptr_not_equal( strstr( stackTraceAsString, __FUNCTION__ ), NULL );
    #endif
}

int run_platform_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTICAPM_CMOCKA_UNIT_TEST( stream_errno ),
        ELASTICAPM_CMOCKA_UNIT_TEST( stream_errno_overflow ),
        ELASTICAPM_CMOCKA_UNIT_TEST( capturing_stack_trace ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
