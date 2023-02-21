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

#include "cmocka_wrapped_for_unit_tests.h"
#include "unit_test_util.h"
#include "platform.h"

#if defined( ELASTIC_APM_ASSUME_CAN_CAPTURE_C_STACK_TRACE ) && ! defined( ELASTIC_APM_CAN_CAPTURE_C_STACK_TRACE )
#error "ELASTIC_APM_ASSUME_CAN_CAPTURE_C_STACK_TRACE is defined but ELASTIC_APM_CAN_CAPTURE_C_STACK_TRACE is not"
#endif

#ifndef NDEBUG
#   define ELASTIC_APM_C_UNIT_TESTS_CAN_PREVENT_INLINE
#endif

#ifndef PHP_WIN32
#   define ELASTIC_APM_C_UNIT_TESTS_NOINLINE_VOID_FUNC void __attribute__ ((noinline))
#else
#   define ELASTIC_APM_C_UNIT_TESTS_NOINLINE_VOID_FUNC __declspec(noinline) void
#endif

#ifndef PHP_WIN32
// To keep this call from being optimized away (search for noinline at https://gcc.gnu.org/onlinedocs/gcc-4.4.7/gcc/Function-Attributes.html)
#   define ELASTIC_APM_C_UNIT_TESTS_NOINLINE_FUNC_BODY_PREFIX asm ("");
#else
#   define ELASTIC_APM_C_UNIT_TESTS_NOINLINE_FUNC_BODY_PREFIX
#endif

enum { testIterateOverCStackTraceContextExpectedFuncsMaxSize = 100 };
struct TestIterateOverCStackTraceContext
{
    String expectedFuncs[ testIterateOverCStackTraceContextExpectedFuncsMaxSize ];
    size_t expectedFuncsSize;
    size_t nextIterationFrameIndex;
};
typedef struct TestIterateOverCStackTraceContext TestIterateOverCStackTraceContext;

static
void pushExpectedFuncToTestContext( TestIterateOverCStackTraceContext* ctx, String expectedFuncName )
{
    ELASTIC_APM_CMOCKA_ASSERT_INT_LESS_THAN( ctx->expectedFuncsSize, testIterateOverCStackTraceContextExpectedFuncsMaxSize );
    ctx->expectedFuncs[ ctx->expectedFuncsSize ] = expectedFuncName;
    ++ctx->expectedFuncsSize;
}

static
String getExpectedFuncFromTestContext( TestIterateOverCStackTraceContext* ctx, size_t expectedFuncIndex )
{
    ELASTIC_APM_CMOCKA_ASSERT_INT_LESS_THAN( expectedFuncIndex, ctx->expectedFuncsSize );
    return ctx->expectedFuncs[ ctx->expectedFuncsSize - expectedFuncIndex - 1 ];
}

static
void test_iterateOverCStackTrace_callback( String frameDesc, void* ctxPVoid )
{
    TestIterateOverCStackTraceContext* ctx = (TestIterateOverCStackTraceContext*)ctxPVoid;
    size_t currentFrameIndex = ctx->nextIterationFrameIndex;
    ++( ctx->nextIterationFrameIndex );
    if ( currentFrameIndex >= ctx->expectedFuncsSize )
    {
        return;
    }
#ifdef ELASTIC_APM_C_UNIT_TESTS_CAN_PREVENT_INLINE
    String expectedFunc = getExpectedFuncFromTestContext( ctx, currentFrameIndex );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE( frameDesc, expectedFunc );
#endif
}

static
void test_iterateOverCStackTrace_logError( String errorDesc, void* ctxPVoid )
{
    TestIterateOverCStackTraceContext* ctx = (TestIterateOverCStackTraceContext*)ctxPVoid;

    fprintf( stderr, "\n" "errorDesc: %s, ctx->nextIterationFrameIndex: %"PRIu64 "\n", errorDesc, (UInt64)(ctx->nextIterationFrameIndex) );
}

ELASTIC_APM_C_UNIT_TESTS_NOINLINE_VOID_FUNC
test_iterateOverCStackTrace_dummy_func_0( TestIterateOverCStackTraceContext* ctx )
{
    ELASTIC_APM_C_UNIT_TESTS_NOINLINE_FUNC_BODY_PREFIX

    pushExpectedFuncToTestContext( ctx, __FUNCTION__  );
    ctx->nextIterationFrameIndex = 0;

#ifdef ELASTIC_APM_CAN_CAPTURE_C_STACK_TRACE
    iterateOverCStackTrace( /* numberOfFramesToSkip */ 0, test_iterateOverCStackTrace_callback, test_iterateOverCStackTrace_logError, ctx );

    ELASTIC_APM_CMOCKA_ASSERT_INT_LESS_THAN
    (
#ifdef ELASTIC_APM_C_UNIT_TESTS_CAN_PREVENT_INLINE
        ctx->expectedFuncsSize
#else
        0
#endif
        , ctx->nextIterationFrameIndex
    );
#endif
}

ELASTIC_APM_C_UNIT_TESTS_NOINLINE_VOID_FUNC
test_iterateOverCStackTrace_dummy_func_1( TestIterateOverCStackTraceContext* ctx )
{
    ELASTIC_APM_C_UNIT_TESTS_NOINLINE_FUNC_BODY_PREFIX

    pushExpectedFuncToTestContext( ctx, __FUNCTION__  );
    test_iterateOverCStackTrace_dummy_func_0( ctx );
}

ELASTIC_APM_C_UNIT_TESTS_NOINLINE_VOID_FUNC
test_iterateOverCStackTrace_dummy_func_2( TestIterateOverCStackTraceContext* ctx )
{
    ELASTIC_APM_C_UNIT_TESTS_NOINLINE_FUNC_BODY_PREFIX

    pushExpectedFuncToTestContext( ctx, __FUNCTION__  );
    test_iterateOverCStackTrace_dummy_func_1( ctx );
}

static
void test_iterateOverCStackTrace( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

#ifdef ELASTIC_APM_CAN_CAPTURE_C_STACK_TRACE
    TestIterateOverCStackTraceContext ctx = { 0 };
    test_iterateOverCStackTrace_dummy_func_2( &ctx );
#else
    ELASTIC_APM_CMOCKA_MARK_CURRENT_TEST_AS_SKIPPED();
#endif
}

int run_iterateOverCStackTrace_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_iterateOverCStackTrace ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
