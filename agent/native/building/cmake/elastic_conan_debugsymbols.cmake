
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
