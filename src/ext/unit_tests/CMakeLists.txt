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

SET( CMAKE_C_STANDARD 99 )

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

IF ( WIN32 )
    ADD_COMPILE_DEFINITIONS( PHP_WIN32 )
    ADD_COMPILE_DEFINITIONS( _CRT_SECURE_NO_WARNINGS )
ENDIF()

#FIND_PACKAGE( cmocka REQUIRED )
FIND_LIBRARY( cmocka_static_lib cmocka )
IF ( NOT cmocka_static_lib )
    IF ( WIN32 )
        IF ( DEFINED ENV{cmocka_installed_dir} )
            SET( cmocka_installed_dir $ENV{cmocka_installed_dir} )
        ELSE()
            MESSAGE( FATAL_ERROR "cmocka_installed_dir environment variable is not defined" )
        ENDIF()
        SET( cmocka_include_dir ${cmocka_installed_dir}/include )
        SET( cmocka_lib_dir ${cmocka_installed_dir}/lib/x64_Debug )
        SET( cmocka_static_lib ${cmocka_lib_dir}/cmocka-static.lib )
    ELSE()
        MESSAGE( FATAL_ERROR "cmocka library not found" )
    ENDIF()
ENDIF()

INCLUDE_DIRECTORIES( . )
INCLUDE_DIRECTORIES( ${src_ext_dir} )
INCLUDE_DIRECTORIES( ${cmocka_include_dir} )

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
LIST( APPEND source_files ${src_ext_dir}/TextOutputStream.h ${src_ext_dir}/TextOutputStream.c )
LIST( APPEND source_files ${src_ext_dir}/time_util.h ${src_ext_dir}/time_util.c )
LIST( APPEND source_files ${src_ext_dir}/Tracer.h ${src_ext_dir}/Tracer.c )
LIST( APPEND source_files ${src_ext_dir}/Tracer.h ${src_ext_dir}/util.c )

IF ( NOT WIN32 )
    ADD_LINK_OPTIONS( -rdynamic )
ENDIF()

ADD_EXECUTABLE( unit_tests ${source_files} )

IF ( NOT WIN32 )
    # Link to library required by math.h and pthread.h
    TARGET_LINK_LIBRARIES( unit_tests m pthread )
ENDIF()

TARGET_LINK_LIBRARIES( unit_tests ${cmocka_static_lib} )

ADD_TEST( NAME Unit_tests COMMAND unit_tests )
