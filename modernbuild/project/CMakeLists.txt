cmake_minimum_required(VERSION 3.26.0)

# set path for our local includes
set(CMAKE_MODULE_PATH ${CMAKE_MODULE_PATH} "${CMAKE_SOURCE_DIR}/cmake")

include(elastic_get_git_version)
elastic_get_git_version(_ELASTIC_PROJECT_VERSION _ELASTIC_SIMPLE_VERSION _ELASTIC_PROJECT_REVISION)

message(STATUS "Project version: ${_ELASTIC_PROJECT_VERSION} (${_ELASTIC_SIMPLE_VERSION}) / ${_ELASTIC_PROJECT_REVISION}")

project("apm-agent-php"
#TODO need to handle versioning somehow and generate C++ and PHP version automatically
    VERSION "${_ELASTIC_PROJECT_VERSION}"
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

if ((NOT ${CMAKE_SYSTEM_NAME} STREQUAL "Linux") OR (NOT ${CMAKE_SYSTEM_PROCESSOR} STREQUAL "x86_64"))
     message(FATAL_ERROR "System or architecure not supported")
endif()

include(elastic_set_default_build_options)

message(STATUS "Detected ${CMAKE_CXX_COMPILER_ID} compiler version: ${CMAKE_CXX_COMPILER_VERSION}")

include(elastic_conan_installer)

# Install project dependencies

set(_supported_php_versions 72 73 74 80 81 82)

function(get_php_api_from_release php_version ret_val)
    block(SCOPE_FOR VARIABLES)
        set(_php_release_72 20170718)
        set(_php_release_73 20180731)
        set(_php_release_74 20190902)
        set(_php_release_80 20200930)
        set(_php_release_81 20210902)
        set(_php_release_82 20220829)

        set(${ret_val} ${_php_release_${php_version}})
        return(PROPAGATE ${ret_val})
    endblock()
endfunction()


message(STATUS "Creating dependencies from local directories")
foreach(_php_version ${_supported_php_versions})
    elastic_conan_create(
        PATH ${CMAKE_SOURCE_DIR}/dependencies/php${_php_version}
        REFERENCE php-headers-${_php_version}/1.0@elastic/local
        PROFILE ${_CONAN_PROFILE}
        )
endforeach()

# TODO implement multiarray with mapping of library->version

set(dependencies
    "libcurl/8.0.1"
    "cmocka/1.1.5"
    "libunwind/1.6.2"
)

foreach(_php_version ${_supported_php_versions})
   list(APPEND dependencies "php-headers-${_php_version}/1.0@elastic/local")
endforeach()

conan_cmake_run(REQUIRES ${dependencies}
                OPTIONS Pkg/*:shared=False
                        boost:header_only=True
                        boost:error_code_header_only=True
                        libcurl:shared=False
                        libcurl:with_libssh2=False
                        libcurl:with_ssl=False
                        cmocka:shared=False
                BUILD missing
                PROFILE ${_CONAN_PROFILE}
                PROFILE_BUILD ${_CONAN_PROFILE}
                DEBUG_PROFILE ${_CONAN_PROFILE}
                RELEASE_PROFILE ${_CONAN_PROFILE}
                BASIC_SETUP CMAKE_TARGETS UPDATE NO_OUTPUT_DIRS
                )

add_subdirectory(agent)
