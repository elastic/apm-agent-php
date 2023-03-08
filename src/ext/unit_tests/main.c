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

#include <stdio.h>
#include <stdlib.h>
#include "basic_types.h"
#include "elastic_apm_is_debug_build.h"
#ifdef _WIN32
#   ifndef WIN32_LEAN_AND_MEAN
#       define WIN32_LEAN_AND_MEAN
#   endif
#   ifndef VC_EXTRALEAN
#       define VC_EXTRALEAN
#   endif
#   include <windows.h>
#endif
#include "gen_numbered_intercepting_callbacks_src.h"

void printInfo();

int run_basic_macros_tests();
int run_basic_types_tests_tests();
int run_util_tests();
int run_IntrusiveDoublyLinkedList_tests();
int run_TextOutputStream_tests();
int run_platform_tests();
int run_DynamicArray_tests();
int run_StringToStringMap_tests();
#if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )
int run_MemoryTracker_tests();
#endif
int run_Logger_tests();
int run_config_tests();
int run_time_util_tests();
int run_iterateOverCStackTrace_tests();
int run_ResultCode_tests();
int run_parse_value_with_units_tests();

int main( int argc, char* argv[] )
{
    printInfo();

    int failedTestsCount = 0;

    failedTestsCount += run_basic_macros_tests();
    failedTestsCount += run_basic_types_tests_tests();
    failedTestsCount += run_util_tests();
    failedTestsCount += run_IntrusiveDoublyLinkedList_tests();
    failedTestsCount += run_TextOutputStream_tests();
    failedTestsCount += run_platform_tests( argc, argv );
    failedTestsCount += run_DynamicArray_tests();
    failedTestsCount += run_StringToStringMap_tests();
        #if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )
    failedTestsCount += run_MemoryTracker_tests();
        #endif
    failedTestsCount += run_Logger_tests();
    failedTestsCount += run_config_tests();
    failedTestsCount += run_time_util_tests();
    failedTestsCount += run_iterateOverCStackTrace_tests();
    failedTestsCount += run_ResultCode_tests();
    failedTestsCount += run_parse_value_with_units_tests();

    // gen_numbered_intercepting_callbacks_src( 1000 );

    return failedTestsCount;
}

static const String cmockaAbortOnFailEnvVarName = "CMOCKA_TEST_ABORT";

void printInfo( int argc, char* argv[] )
{
    puts( "#####################################################################" );
    puts( "##################################" );
    puts( "################" );
    puts( "####" );
    puts( "" );

    printf( "argc: %d\n", argc );
    for ( int i = 0 ; i < argc ; ++i)
    {
        printf( "argv[%d]: %s\n", i, argv[i] );
    }

    String NDEBUG_defined =
    #ifdef NDEBUG
            "yes"
    #else
            "no"
    #endif
    ;
    printf( "NDEBUG defined: %s\n", NDEBUG_defined );

    printf( "ELASTIC_APM_IS_DEBUG_BUILD_01: %u\n", ELASTIC_APM_IS_DEBUG_BUILD_01 );

    printf( "Environment variable %s: %s\n", cmockaAbortOnFailEnvVarName, getenv( cmockaAbortOnFailEnvVarName ) );

    puts( "" );
    puts( "####" );
    puts( "################" );
    puts( "##################################" );
    puts( "#####################################################################" );
}
