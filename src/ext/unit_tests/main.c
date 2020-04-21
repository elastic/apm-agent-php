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

#include <stdio.h>
#include <stdlib.h>
#include "basic_types.h"
#include "elasticapm_is_debug_build.h"
#ifdef _WIN32
#   ifndef WIN32_LEAN_AND_MEAN
#       define WIN32_LEAN_AND_MEAN
#   endif
#   ifndef VC_EXTRALEAN
#       define VC_EXTRALEAN
#   endif
#   include <windows.h>
#endif

void printInfo();

int run_basic_macros_tests();
int run_basic_types_tests_tests();
int run_util_tests();
int run_IntrusiveDoublyLinkedList_tests();
int run_TextOutputStream_tests();
int run_platform_tests();
int run_DynamicArray_tests();
int run_StringToStringMap_tests();
#if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )
int run_MemoryTracker_tests();
#endif
int run_Logger_tests();
int run_config_tests();

int main( int argc, char* argv[] )
{
    printInfo();

    int failedTestsCount = 0;

    failedTestsCount += run_basic_macros_tests();
    failedTestsCount += run_basic_types_tests_tests();
    failedTestsCount += run_util_tests();
    failedTestsCount += run_IntrusiveDoublyLinkedList_tests();
    failedTestsCount += run_TextOutputStream_tests();
    failedTestsCount += run_platform_tests();
    failedTestsCount += run_DynamicArray_tests();
    failedTestsCount += run_StringToStringMap_tests();
        #if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )
    failedTestsCount += run_MemoryTracker_tests();
        #endif
    failedTestsCount += run_Logger_tests();
    failedTestsCount += run_config_tests();

    return failedTestsCount;
}

static const String cmockaAbortOnFailEnvVarName = "CMOCKA_TEST_ABORT";

void printInfo()
{
    puts( "#####################################################################" );
    puts( "##################################" );
    puts( "################" );
    puts( "####" );
    puts( "" );

    String NDEBUG_defined =
    #ifdef NDEBUG
            "yes"
    #else
            "no"
    #endif
    ;
    printf( "NDEBUG defined: %s\n", NDEBUG_defined );

    printf( "ELASTICAPM_IS_DEBUG_BUILD_01: %u\n", ELASTICAPM_IS_DEBUG_BUILD_01 );

    printf( "Environment variable %s: %s\n", cmockaAbortOnFailEnvVarName, getenv( cmockaAbortOnFailEnvVarName ) );

    puts( "" );
    puts( "####" );
    puts( "################" );
    puts( "##################################" );
    puts( "#####################################################################" );
}
