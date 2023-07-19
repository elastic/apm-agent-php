include(CMakeParseArguments)



set(python "python3")
set(python_pip "pip3")

# Detect Alpine/MUSLC build
if(EXISTS /etc/alpine-release)
  set(MUSL_BUILD true)
endif()

message(STATUS "Enabling python virtual environment")

set(VENV_PATH ${CMAKE_BINARY_DIR}/python_venv)

if(NOT EXISTS ${VENV_PATH})
    execute_process(
        COMMAND ${python} -m venv ${VENV_PATH}
        COMMAND_ERROR_IS_FATAL ANY
        )
    file(COPY ${CMAKE_SOURCE_DIR}/building/cmake/test_venv.py DESTINATION ${CMAKE_BINARY_DIR}/)
    set(_VENV_CREATED TRUE)
endif()

set(ENV{VIRTUAL_ENV} ${VENV_PATH})
set(ENV{PATH} $ENV{VIRTUAL_ENV}/bin:$ENV{PATH})
set(python "${VENV_PATH}/bin/python3")
set(python_pip "${VENV_PATH}/bin/pip3")

message(STATUS PATH="$ENV{PATH}")


# Testing if venv is detected propely by python
execute_process(
    COMMAND ${python} ${CMAKE_BINARY_DIR}/test_venv.py
    COMMAND_ERROR_IS_FATAL ANY
    )

set(_PRV_CONAN_PROFILE_OS ${CMAKE_SYSTEM_NAME})

if (MUSL_BUILD)
    #this is workaround to force build from souce on musl - this will prevent from installing libc binaries
    set(_PRV_CONAN_PROFILE_OS_DISTRO "os.distro=Alpine")
    set(_PRV_COMPILER_LIBC_IMPLEMENTATION "compiler.libc=musl")
elseif (${CMAKE_SYSTEM_NAME} STREQUAL "Linux")
    set(_PRV_CONAN_PROFILE_OS_DISTRO "os.distro=Centos7")
    set(_PRV_COMPILER_LIBC_IMPLEMENTATION "compiler.libc=glibc")
endif()

if (${CMAKE_SYSTEM_NAME} STREQUAL "Linux")
    message(STATUS "Linux ${_PRV_CONAN_PROFILE_OS_DISTRO}, ${_PRV_COMPILER_LIBC_IMPLEMENTATION}")
endif()

# setting up paths used to configure compiler profile
set(_PRV_CONAN_PROFILE_CC ${CMAKE_C_COMPILER})
set(_PRV_CONAN_PROFILE_CXX ${CMAKE_CXX_COMPILER})

# some recipes doesn't use profile compiler, it is a workaround
if (CMAKE_CXX_COMPILER_ID STREQUAL "GNU")
    list(APPEND CMAKE_PROGRAM_PATH "$ENV{COMPILER_HOME_PATH}/bin/")
    set(ENV{PATH} $ENV{COMPILER_HOME_PATH}/bin:$ENV{PATH})
endif()

# Prepare conan profile
set(_PRV_COMPILER_NAME gcc)
string(REPLACE "." ";" _PRV_COMPILER_VERSION_TOKENIZED ${CMAKE_CXX_COMPILER_VERSION})
list(GET _PRV_COMPILER_VERSION_TOKENIZED 0 _PRV_COMPILER_VERSION_MAJOR)
list(GET _PRV_COMPILER_VERSION_TOKENIZED 1 _PRV_COMPILER_VERSION_MINOR)
set(_PRV_COMPILER_VERSION_SHORT "${_PRV_COMPILER_VERSION_MAJOR}.${_PRV_COMPILER_VERSION_MINOR}")
set(_CONAN_PROFILE "${CMAKE_BINARY_DIR}/conan_compiler")

configure_file("${CMAKE_SOURCE_DIR}/building/conan/conan_profile.in" "${_CONAN_PROFILE}" @ONLY)


if(_VENV_CREATED)
    # Installing conan and required dependencies 
    execute_process(
        COMMAND ${python_pip} install -U pip
        COMMAND_ERROR_IS_FATAL ANY
    )

    execute_process(
        COMMAND ${python_pip} install -U "pyyaml==3.11"
        COMMAND_ERROR_IS_FATAL ANY
    )

    execute_process(
        COMMAND ${python_pip} install -U "pyyaml==3.11"
        COMMAND_ERROR_IS_FATAL ANY
    )

    execute_process(
        COMMAND ${python_pip} install -U conan==1.60.0
        COMMAND_ERROR_IS_FATAL ANY
    )

endif()

message(STATUS "Installing conan configuration from ${CMAKE_SOURCE_DIR}/building/conan/settings.yml")
execute_process(
    COMMAND conan config install ${CMAKE_SOURCE_DIR}/building/conan/settings.yml
    COMMAND_ERROR_IS_FATAL ANY
)

message(STATUS "Conan installation done")

include(conan)
conan_check()

include(elastic_conan_export)
