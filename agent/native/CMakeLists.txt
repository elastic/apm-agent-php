cmake_minimum_required(VERSION 3.26.0)

# set path for our local includes
set(CMAKE_MODULE_PATH ${CMAKE_MODULE_PATH} "${CMAKE_SOURCE_DIR}/building/cmake")

#include(elastic_get_git_version)
#elastic_get_git_version(_ELASTIC_PROJECT_VERSION _ELASTIC_SIMPLE_VERSION _ELASTIC_PROJECT_REVISION)

#message(STATUS "Project version: ${_ELASTIC_PROJECT_VERSION} (${_ELASTIC_SIMPLE_VERSION}) / ${_ELASTIC_PROJECT_REVISION}")

project("apm-agent-php"
#TODO need to handle versioning somehow and generate C++ and PHP version automatically
 #   VERSION "${_ELASTIC_PROJECT_VERSION}"
    VERSION "1.0"
    LANGUAGES C CXX
)

set(RELEASE_BUILD false)
set(DEBUG_BUILD false)
if(CMAKE_BUILD_TYPE STREQUAL "Release")
    set(RELEASE_BUILD true)
elseif(CMAKE_BUILD_TYPE STREQUAL "Debug")
    set(DEBUG_BUILD true)
else()
    message(FATAL_ERROR "Build type not supported: ${CMAKE_BUILD_TYPE}")
endif()

# Detect Alpine/MUSLC build
if(EXISTS /etc/alpine-release)
    set(MUSL_BUILD true)
endif()

if ((NOT ${CMAKE_SYSTEM_NAME} STREQUAL "Linux") OR (NOT ${CMAKE_SYSTEM_PROCESSOR} STREQUAL "x86_64" AND NOT ${CMAKE_SYSTEM_PROCESSOR} STREQUAL "aarch64"))
     message(FATAL_ERROR "System or architecture not supported")
endif()

include(elastic_set_default_build_options)

message(STATUS "Detected ${CMAKE_CXX_COMPILER_ID} compiler version: ${CMAKE_CXX_COMPILER_VERSION}")

include(elastic_conan_installer)
include(elastic_conan_debugsymbols)

# Install project dependencies

set(_supported_php_versions 72 73 74 80 81 82 83 84)

function(get_php_api_from_release php_version ret_val)
    block(SCOPE_FOR VARIABLES)
        set(_php_release_72 20170718)
        set(_php_release_73 20180731)
        set(_php_release_74 20190902)
        set(_php_release_80 20200930)
        set(_php_release_81 20210902)
        set(_php_release_82 20220829)
        set(_php_release_83 20230831)
        set(_php_release_84 20240924)

        set(${ret_val} ${_php_release_${php_version}})
        return(PROPAGATE ${ret_val})
    endblock()
endfunction()


message(STATUS "Creating dependencies from local directories")

# build pkgconf from local reciepe because default one rely on unstable sources host, we're fetching from github tag
elastic_conan_create(
    PATH ${CMAKE_SOURCE_DIR}/building/dependencies/pkgconf
    REFERENCE pkgconf/1.9.3@elastic/local
    PROFILE ${_CONAN_PROFILE}
    )

elastic_conan_alias(
    REFERENCE pkgconf/1.9.3
    TARGET pkgconf/1.9.3@elastic/local
)

foreach(_php_version ${_supported_php_versions})
    elastic_conan_create(
        PATH ${CMAKE_SOURCE_DIR}/building/dependencies/php${_php_version}
        REFERENCE php-headers-${_php_version}/1.0@elastic/local
        PROFILE ${_CONAN_PROFILE}
        )
endforeach()



# TODO implement multiarray with mapping of library->version

set(dependencies
    "zlib/1.3"
    "libcurl/8.0.1"
    "libunwind/1.6.2"
    "boost/1.82.0"
    "gtest/1.13.0"
)

# cmocka has issues on arm64
if (NOT ${CMAKE_SYSTEM_PROCESSOR} STREQUAL "aarch64")
    list(APPEND dependencies "cmocka/1.1.5@")
endif()

foreach(_php_version ${_supported_php_versions})
   list(APPEND dependencies "php-headers-${_php_version}/1.0@elastic/local")
endforeach()

conan_cmake_run(REQUIRES ${dependencies}
                OPTIONS Pkg/*:shared=False
                        boost:header_only=True
                        libcurl:shared=True
                        libcurl:with_libssh2=True
                BUILD missing
                PROFILE ${_CONAN_PROFILE}
                PROFILE_BUILD ${_CONAN_PROFILE}
                DEBUG_PROFILE ${_CONAN_PROFILE}
                RELEASE_PROFILE ${_CONAN_PROFILE}
                BASIC_SETUP CMAKE_TARGETS UPDATE NO_OUTPUT_DIRS
                )

enable_testing()

add_subdirectory(libcommon)
add_subdirectory(libphpbridge)
add_subdirectory(loader)

add_subdirectory(ext)
