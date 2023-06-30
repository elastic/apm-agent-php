
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
