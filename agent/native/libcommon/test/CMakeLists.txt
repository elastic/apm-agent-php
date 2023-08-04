
#scan for source files
AUX_SOURCE_DIRECTORY(. SrcFiles)

set(testLib libcommon)

set (targetName  "${testLib}_test")

add_executable(${targetName} ${SrcFiles})

target_link_libraries(${targetName}
    PRIVATE ${testLib}
    CONAN_PKG::gtest)

target_include_directories(${targetName}
    PRIVATE "code"
    "${CONAN_INCLUDE_DIRS_GTEST}")


add_test(NAME ${targetName}
    COMMAND ${targetName})
