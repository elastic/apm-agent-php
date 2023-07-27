#scan for source files

AUX_SOURCE_DIRECTORY(. SrcFiles)

set (_Target  libcommon)

add_library (${_Target}
    STATIC ${SrcFiles}
)
target_include_directories(${_Target} PUBLIC "./")

