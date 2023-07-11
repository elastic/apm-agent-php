#scan for source files

AUX_SOURCE_DIRECTORY(. SrcFiles)

set (_Target  libcommon)

# target_link_libraries(${_Target}
# PRIVATE CONAN_PKG::libcurl
# PRIVATE CONAN_PKG::libunwind
# )



add_library (${_Target}
    STATIC ${SrcFiles}
)
target_include_directories(${_Target} PUBLIC "./")

