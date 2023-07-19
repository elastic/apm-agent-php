

# Constrol switches
set(_ELASTIC_FAIL_ON_ERROR true)
set(_ELASTIC_WARN_ON_UNINITIALIZED true)


# determine build type and set 
set(MUSL_BUILD false)

set_property(GLOBAL PROPERTY GLOBAL_DEPENDS_NO_CYCLES ON) # https://cmake.org/cmake/help/latest/prop_gbl/GLOBAL_DEPENDS_NO_CYCLES.html

# Set the defauts for all targets
set(CMAKE_DISABLE_IN_SOURCE_BUILD ON)   # https://github.com/ComputationalRadiationPhysics/picongpu/issues/2109
set(CMAKE_DISABLE_SOURCE_CHANGES  ON)
set(CMAKE_CXX_EXTENSIONS OFF)           # https://cmake.org/cmake/help/latest/prop_tgt/CXX_EXTENSIONS.html#prop_tgt:CXX_EXTENSIONS
set(CMAKE_CXX_STANDARD_REQUIRED ON)     # https://cmake.org/cmake/help/latest/prop_tgt/CXX_STANDARD_REQUIRED.html#prop_tgt:CXX_STANDARD_REQUIRED
set(CMAKE_CXX_STANDARD 20)
set(CMAKE_INCLUDE_CURRENT_DIR ON)       # https://cmake.org/cmake/help/latest/variable/CMAKE_INCLUDE_CURRENT_DIR.html

set(CMAKE_BUILD_WITH_INSTALL_RPATH ON)  # include runtime search path for shared libs

# set up visibility policy for dynamic linking
set(CMAKE_C_VISIBILITY_PRESET hidden)   # https://cmake.org/cmake/help/latest/policy/CMP0063.html#policy:CMP0063
set(CMAKE_CXX_VISIBILITY_PRESET hidden)

set(CMAKE_POSITION_INDEPENDENT_CODE ON) # https://cmake.org/cmake/help/latest/variable/CMAKE_POSITION_INDEPENDENT_CODE.html#variable:CMAKE_POSITION_INDEPENDENT_CODE

# Enabling pthreads - https://cmake.org/cmake/help/v3.2/module/FindThreads.html
set(CMAKE_THREAD_PREFER_PTHREAD ON)
set(THREADS_PREFER_PTHREAD_FLAG ON)
find_package(Threads REQUIRED)


# Keep output small as possible and manually control what we want to link with binaries
set(CMAKE_C_IMPLICIT_LINK_LIBRARIES "") 
set(CMAKE_C_IMPLICIT_LINK_DIRECTORIES "") 

# Workaround to enable globally staticaly linked libgcc and libstdc++ - don't need to be enabled foreach target
# linking with libdl and libpthreads
# Bsymbolic - use our own symbols instead of PHP one - prevent curl issues (https://www.intel.com/content/www/us/en/docs/cpp-compiler/developer-guide-reference/2021-8/bsymbolic-functions.html)
set(CMAKE_SHARED_LINKER_FLAGS "${CMAKE_SHARED_LINKER_FLAGS} -static-libgcc -static-libstdc++ -pthread -ldl -Wl,-Bsymbolic -Wl,--exclude-libs,ALL")
set(CMAKE_EXE_LINKER_FLAGS "${CMAKE_EXE_LINKER_FLAGS} -static-libgcc -static-libstdc++ -pthread")

add_compile_options("-pipe") # don't use temporary files but pipe data to linker

# Set up optimizations
if(RELEASE_BUILD) 
    add_compile_options("-O2"
                        "-g"
    )
    add_definitions("-DNDEBUG")
    add_definitions("-D_FORTIFY_SOURCE=2")
elseif(DEBUG_BUILD)
    add_compile_options("-O0"
                        "-g3")
endif()

add_compile_options("-pthread"
                    "-fexceptions" # Enable exception handling in C to interoperate properly with exception handlers written in C++ (https://gcc.gnu.org/onlinedocs/gcc/Code-Gen-Options.html)
                    "-fstack-protector-strong"
                    )


# handling warnings
add_compile_options(
                    "-Wall"
                    "-Wextra"
                    "-Wno-unused-parameter" # annoying when using PHP delivered macros
                    "-Wno-ignored-qualifiers" #TODO php headers issues, should be removed and patched in conan package
                    "-Wno-unknown-pragmas" #clion, windows pragmas
                    "-Wno-unused-local-typedefs"
                    "-Wno-enum-compare" #TODO refactor code and remove
                    "-Wno-write-strings"

)

if(NOT _ELASTIC_WARN_ON_UNINITIALIZED)
    add_compile_options("-Wno-maybe-uninitialized")
endif()

if(_ELASTIC_FAIL_ON_ERROR)
    add_compile_options("-Werror")
endif()

# C++ only switches
add_compile_options("$<$<COMPILE_LANGUAGE:CXX>:-Wno-register>")
add_compile_options("$<$<COMPILE_LANGUAGE:CXX>:-Wnon-virtual-dtor>")
add_compile_options("$<$<COMPILE_LANGUAGE:CXX>:-fdiagnostics-show-template-tree>") # print template mismatch as tree - much more user friendly 

# C only switches
add_compile_options("$<$<COMPILE_LANGUAGE:C>:-Wstrict-prototypes>")
