
#scan for source files
AUX_SOURCE_DIRECTORY(. SrcFiles)

function(copy_debug_symbols target)
    block(SCOPE_FOR VARIABLES)
        get_target_property(_TargetType ${target} TYPE)

        add_custom_command(TARGET ${target}
            POST_BUILD
            COMMAND
                ${CMAKE_OBJCOPY} "--only-keep-debug" "$<TARGET_FILE:${target}>" "$<TARGET_FILE_DIR:${target}>/$<TARGET_PROPERTY:${target},DEBUG_SYMBOL_FILE>"

            COMMAND
                ${CMAKE_OBJCOPY}
                "--add-gnu-debuglink=$<TARGET_FILE_DIR:${target}>/$<TARGET_PROPERTY:${target},DEBUG_SYMBOL_FILE>"
                "--strip-debug" "--strip-unneeded"
                "$<TARGET_FILE:${target}>"
            COMMENT "Striped debug symbols from ${target}"
            )

    endblock()
endfunction()


foreach(_php_version ${_supported_php_versions})
    set (_Target  elasticapm_${_php_version})

    add_library (${_Target}
        SHARED ${SrcFiles}
        )

    target_compile_definitions(${_Target}
                PRIVATE
                "PHP_ATOM_INC"
                "PHP_ABI=${CMAKE_C_COMPILER_ABI}")

    if(MUSL_BUILD)
        target_compile_definitions(${_Target}
                PRIVATE
                "__ELASTIC_LIBC_MUSL__"
        )
    endif()

    target_include_directories(${_Target} PUBLIC "${CONAN_INCLUDE_DIRS_PHP-HEADERS-${_php_version}}"
                                                "${CONAN_INCLUDE_DIRS_PHP-HEADERS-${_php_version}}/ext"
                                                "${CONAN_INCLUDE_DIRS_PHP-HEADERS-${_php_version}}/main"
                                                "${CONAN_INCLUDE_DIRS_PHP-HEADERS-${_php_version}}/TSRM"
                                                "${CONAN_INCLUDE_DIRS_PHP-HEADERS-${_php_version}}/Zend"
                                                "${CONAN_INCLUDE_DIRS_LIBCURL}"
                                                "${CONAN_INCLUDE_DIRS_LIBUNWIND}"
                                            )

    target_link_libraries(${_Target}
                PRIVATE CONAN_PKG::libcurl
                PRIVATE CONAN_PKG::libunwind
    )

    get_php_api_from_release(${_php_version} _ZEND_API_version)

    set_target_properties(${_Target}
        PROPERTIES OUTPUT_NAME elastic_apm-${_ZEND_API_version}
        PREFIX ""
    )

    set_target_properties(${_Target}
        PROPERTIES OUTPUT_NAME elastic_apm-${_ZEND_API_version}
        PREFIX ""
        DEBUG_SYMBOL_FILE "elastic_apm-${_ZEND_API_version}.debug"
    )

    if (RELEASE_BUILD)
        copy_debug_symbols(${_Target})
    endif()

endforeach()


add_subdirectory(unit_tests)