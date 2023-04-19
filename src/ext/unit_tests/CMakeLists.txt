# +----------------------------------------------------------------------+
# | Elastic APM agent for PHP                                            |
# +----------------------------------------------------------------------+
# | Copyright (c) 2020 Elasticsearch B.V.                                |
# +----------------------------------------------------------------------+
# | Elasticsearch B.V. licenses this file under the Apache 2.0 License.  |
# | See the LICENSE file in the project root for more information.       |
# +----------------------------------------------------------------------+

CMAKE_MINIMUM_REQUIRED( VERSION 3.15 )

#IF ( WIN32 )
#    # From https://github.com/microsoft/vcpkg/blob/master/docs/users/integration.md#using-an-environment-variable-instead-of-a-command-line-option
#    IF ( DEFINED ENV{VCPKG_ROOT} AND NOT DEFINED CMAKE_TOOLCHAIN_FILE )
#        SET( CMAKE_TOOLCHAIN_FILE "$ENV{VCPKG_ROOT}/scripts/buildsystems/vcpkg.cmake"
#             CACHE STRING "" )
#        MESSAGE( "Set CMAKE_TOOLCHAIN_FILE to ${CMAKE_TOOLCHAIN_FILE}" )
#    ENDIF ()
#ENDIF ()

PROJECT( unit_tests C )

# disable warnings - fix tests and remove
add_compile_options("-Wno-comment")
add_compile_options("-Wno-enum-compare")
add_compile_options("-Wno-unused-local-typedefs")
add_compile_options("-Wno-unused-function")
add_compile_options("-Wno-sign-compare")
add_compile_options("-Wno-type-limits")
add_compile_options("-Wno-unused-variable")


SET( CMAKE_C_STANDARD 99 )

SET( CMAKE_COMPILE_WARNING_AS_ERROR ON )

# Always include srcdir and builddir in include path
# This saves typing ${CMAKE_CURRENT_SOURCE_DIR} ${CMAKE_CURRENT_BINARY} in
# about every subdir
# since cmake 2.4.0
SET( CMAKE_INCLUDE_CURRENT_DIR ON )

# Put the include dirs which are in the source or build tree
# before all other include dirs, so the headers in the sources
# are preferred over the already installed ones
# since cmake 2.4.1
SET( CMAKE_INCLUDE_DIRECTORIES_PROJECT_BEFORE ON )

# Use colored output
# since cmake 2.4.0
SET( CMAKE_COLOR_MAKEFILE ON )

# Create the compile command database for clang by default
SET( CMAKE_EXPORT_COMPILE_COMMANDS ON )

# Always build with -fPIC
SET( CMAKE_POSITION_INDEPENDENT_CODE ON )

# Avoid source tree pollution
SET( CMAKE_DISABLE_SOURCE_CHANGES ON )
SET( CMAKE_DISABLE_IN_SOURCE_BUILD ON )

SET( src_ext_dir ${CMAKE_CURRENT_SOURCE_DIR}/.. )

ADD_COMPILE_DEFINITIONS( ELASTIC_APM_ASSERT_FAILED_FUNC=productionCodeAssertFailed )
ADD_COMPILE_DEFINITIONS( ELASTIC_APM_PEMALLOC_FUNC=productionCodePeMalloc )
ADD_COMPILE_DEFINITIONS( ELASTIC_APM_PEFREE_FUNC=productionCodePeFree )
ADD_COMPILE_DEFINITIONS( ELASTIC_APM_MOCK_CLOCK )
ADD_COMPILE_DEFINITIONS( ELASTIC_APM_LOG_CUSTOM_SINK_FUNC=writeToMockLogCustomSink )
ADD_COMPILE_DEFINITIONS( ELASTIC_APM_MOCK_PHP_DEPS )
ADD_COMPILE_DEFINITIONS( ELASTIC_APM_MOCK_STDLIB )
ADD_COMPILE_DEFINITIONS( ELASTIC_APM_GETENV_FUNC=mockGetEnv )
ADD_COMPILE_DEFINITIONS( ELASTIC_APM_INTERNAL_CHECKS_DEFAULT_LEVEL=internalChecksLevel_all )
ADD_COMPILE_DEFINITIONS( ELASTIC_APM_ASSERT_DEFAULT_LEVEL=assertLevel_all )
ADD_COMPILE_DEFINITIONS( ELASTIC_APM_MEMORY_TRACKING_DEFAULT_LEVEL=memoryTrackingLevel_all )
ADD_COMPILE_DEFINITIONS( ELASTIC_APM_MEMORY_TRACKING_DEFAULT_ABORT_ON_MEMORY_LEAK=true )
ADD_COMPILE_DEFINITIONS( ELASTIC_APM_ON_MEMORY_LEAK_CUSTOM_FUNC=onMemoryLeakDuringUnitTests )

IF ( $ENV{CLION_IDE} )
    ADD_COMPILE_DEFINITIONS( ELASTIC_APM_UNDER_IDE )
ENDIF()

IF ( "$ENV{ELASTIC_APM_ASSUME_CAN_CAPTURE_C_STACK_TRACE}" STREQUAL "true" )
    ADD_COMPILE_DEFINITIONS( ELASTIC_APM_ASSUME_CAN_CAPTURE_C_STACK_TRACE )
ENDIF()

IF ( WIN32 )
    ADD_COMPILE_DEFINITIONS( PHP_WIN32 )
    ADD_COMPILE_DEFINITIONS( _CRT_SECURE_NO_WARNINGS )
ENDIF()

INCLUDE_DIRECTORIES( . )
INCLUDE_DIRECTORIES( ${src_ext_dir} )
INCLUDE_DIRECTORIES( ${CMOCKA_INCLUDE_DIR} )

FILE( GLOB unit_tests_source_files *.c *.h )
LIST( APPEND source_files ${unit_tests_source_files} )

LIST( APPEND source_files ${src_ext_dir}/TextOutputStream.h )

LIST( APPEND source_files ${src_ext_dir}/ConfigManager.h ${src_ext_dir}/ConfigManager.c )
LIST( APPEND source_files ${src_ext_dir}/elastic_apm_assert.h ${src_ext_dir}/elastic_apm_assert.c )
LIST( APPEND source_files ${src_ext_dir}/internal_checks.h ${src_ext_dir}/internal_checks.c )
LIST( APPEND source_files ${src_ext_dir}/log.h ${src_ext_dir}/log.c )
LIST( APPEND source_files ${src_ext_dir}/MemoryTracker.h ${src_ext_dir}/MemoryTracker.c )
LIST( APPEND source_files ${src_ext_dir}/platform.h ${src_ext_dir}/platform.c )
LIST( APPEND source_files ${src_ext_dir}/platform_threads.h ${src_ext_dir}/platform_threads_linux.c )
LIST( APPEND source_files ${src_ext_dir}/ResultCode.h ${src_ext_dir}/ResultCode.c )
LIST( APPEND source_files ${src_ext_dir}/TextOutputStream.h ${src_ext_dir}/TextOutputStream.c )
LIST( APPEND source_files ${src_ext_dir}/time_util.h ${src_ext_dir}/time_util.c )
LIST( APPEND source_files ${src_ext_dir}/Tracer.h ${src_ext_dir}/Tracer.c )
LIST( APPEND source_files ${src_ext_dir}/Tracer.h ${src_ext_dir}/util.c )

IF ( NOT WIN32 )
    ADD_LINK_OPTIONS( -rdynamic )
ENDIF()

ADD_EXECUTABLE( unit_tests ${source_files} )

IF ( NOT WIN32 )
    # Link to library required by math.h 
    SET( link_with_libraries ${link_with_libraries} m )
ENDIF()

target_link_libraries( unit_tests PRIVATE CONAN_PKG::cmocka
                                PRIVATE CONAN_PKG::libunwind
                                Threads::Threads
                                m
                                )

ADD_TEST( NAME Unit_tests COMMAND unit_tests )
