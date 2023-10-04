
#scan for source files
AUX_SOURCE_DIRECTORY(. SrcFiles)

set (_Target elasticapm_loader)

add_library (${_Target} SHARED ${SrcFiles})

target_link_libraries(${_Target}
    PRIVATE libcommon
)

set_target_properties(${_Target}
    PROPERTIES OUTPUT_NAME elastic_apm_loader
    PREFIX ""
    DEBUG_SYMBOL_FILE "elastic_apm_loader.debug"
)

if (RELEASE_BUILD)
    copy_debug_symbols(${_Target})
endif()

