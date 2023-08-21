# AUX_SOURCE_DIRECTORY(. SrcFiles)

# set (_Target  libphpbridge)


# add_library (${_Target}
#     STATIC ${SrcFiles}
# )




#scan for source files
AUX_SOURCE_DIRECTORY(. SrcFiles)

foreach(_php_version ${_supported_php_versions})
    set (_Target  libphpbridge_${_php_version})

    add_library (${_Target}
        STATIC ${SrcFiles}
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


    target_include_directories(${_Target} PUBLIC "./")

    target_include_directories(${_Target} PRIVATE "${CONAN_INCLUDE_DIRS_PHP-HEADERS-${_php_version}}"
                                                "${CONAN_INCLUDE_DIRS_PHP-HEADERS-${_php_version}}/ext"
                                                "${CONAN_INCLUDE_DIRS_PHP-HEADERS-${_php_version}}/main"
                                                "${CONAN_INCLUDE_DIRS_PHP-HEADERS-${_php_version}}/TSRM"
                                                "${CONAN_INCLUDE_DIRS_PHP-HEADERS-${_php_version}}/Zend"
                                            )

    target_link_libraries(${_Target}
        libcommon
    )

endforeach()
