
include(CMakeParseArguments)

function(elastic_conan_export)
    set(oneValueArgs PATH REFERENCE PROFILE)
    cmake_parse_arguments(_elastic_conan_export "" "${oneValueArgs}" "" ${ARGN} )

    message(STATUS "conan export path: ${_elastic_conan_export_PATH} ref: ${_elastic_conan_export_REFERENCE} profile: ${_elastic_conan_export_PROFILE}" )

    execute_process(COMMAND ${CONAN_CMD} export ${_elastic_conan_export_PATH} ${_elastic_conan_export_REFERENCE} )    
endfunction()

function(elastic_conan_create)
    set(oneValueArgs PATH REFERENCE PROFILE)
    cmake_parse_arguments(_elastic_conan_create "" "${oneValueArgs}" "" ${ARGN} )

    execute_process(COMMAND ${CONAN_CMD} search --raw ${_elastic_conan_create_REFERENCE}
        RESULT_VARIABLE return_code
        OUTPUT_QUIET
        ERROR_QUIET
    )

    if ("${return_code}" STREQUAL 0)
        message(STATUS "Package ${_elastic_conan_create_REFERENCE} already installed")
        return()
    endif()

    message(STATUS "${CONAN_CMD} create  --build=missing -pr ${_elastic_conan_create_PROFILE}  ${_elastic_conan_create_PATH} ${_elastic_conan_create_REFERENCE}" )
    execute_process(COMMAND ${CONAN_CMD} create --build=missing -pr ${_elastic_conan_create_PROFILE}  ${_elastic_conan_create_PATH} ${_elastic_conan_create_REFERENCE}
                    COMMAND_ERROR_IS_FATAL ANY
    )
endfunction()


function(elastic_conan_alias)
    set(oneValueArgs PATH REFERENCE TARGET)
    cmake_parse_arguments(_elastic_conan_alias "" "${oneValueArgs}" "" ${ARGN} )

    message(STATUS "${CONAN_CMD} alias ${_elastic_conan_alias_REFERENCE} ${_elastic_conan_alias_TARGET}")
    execute_process(COMMAND ${CONAN_CMD} alias ${_elastic_conan_alias_REFERENCE} ${_elastic_conan_alias_TARGET}
                    COMMAND_ERROR_IS_FATAL ANY
    )
endfunction()

function(conan_update_remote)
    # Update a remote
    # Arguments URL and NAME are required, INDEX, COMMAND and VERIFY_SSL are optional
    # Example usage:
    #    conan_add_remote(NAME bincrafters INDEX 1
    #       URL https://api.bintray.com/conan/bincrafters/public-conan
    #       VERIFY_SSL True)
    set(oneValueArgs URL NAME INDEX COMMAND VERIFY_SSL)
    cmake_parse_arguments(CONAN "" "${oneValueArgs}" "" ${ARGN})

    if(DEFINED CONAN_INDEX)
        set(CONAN_INDEX_ARG "-i ${CONAN_INDEX}")
    endif()
    if(DEFINED CONAN_COMMAND)
        set(CONAN_CMD ${CONAN_COMMAND})
    else()
        conan_check(REQUIRED DETECT_QUIET)
    endif()
    set(CONAN_VERIFY_SSL_ARG "True")
    if(DEFINED CONAN_VERIFY_SSL)
        set(CONAN_VERIFY_SSL_ARG ${CONAN_VERIFY_SSL})
    endif()
    message(STATUS "Conan: Updating ${CONAN_NAME} remote repository (${CONAN_URL}) verify ssl (${CONAN_VERIFY_SSL_ARG})")
    execute_process(COMMAND ${CONAN_CMD} remote update ${CONAN_NAME} ${CONAN_INDEX_ARG} ${CONAN_URL} ${CONAN_VERIFY_SSL_ARG}
                    RESULT_VARIABLE return_code)
    if(NOT "${return_code}" STREQUAL "0")
      message(FATAL_ERROR "Conan remote failed='${return_code}'")
    endif()
endfunction()


function(conan_remove_remote)
    # Update a remote
    # Argument NAME is required
    # Example usage:
    #    conan_remove_remote(NAME bincrafters)
    set(oneValueArgs NAME)
    cmake_parse_arguments(CONAN "" "${oneValueArgs}" "" ${ARGN})

    message(STATUS "Conan: Removing ${CONAN_NAME} remote repository")
    execute_process(COMMAND ${CONAN_CMD} remote remove ${CONAN_NAME}
                    RESULT_VARIABLE return_code)
    if(NOT "${return_code}" STREQUAL "0")
      message(FATAL_ERROR "Conan remote failed='${return_code}'")
    endif()
endfunction()