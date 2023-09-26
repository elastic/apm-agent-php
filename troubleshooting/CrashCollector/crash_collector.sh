#!/bin/bash

# Required toolset:
#   awk
#   eu-readelf
#   file - if notbinary provided
#   grep
#   ldd
#   readelf
#   sed
#   tar

function show_help {

echo -e "$0 -c CORE_FILE [-f EXE_PATH ] [ -o OUTPUT_PATH ]

Arguments description:
    -c core_file            required, core dump file to analyse
    -e executable_path      executable path, optional if file is installed
    -o ouput_path           path where core dump archive should be created
    -h                      print this help
    -v                      verbose mode
"
}

OPT_EXEFILE=""
OPT_COREFILE=""
OPT_OUTPUT=""

while getopts "vc:e:o:h" opt; do
    case "$opt" in
    h|\?)
        show_help
        exit 0
        ;;
    c)  OPT_COREFILE="${OPTARG}"
        ;;
    e)  OPT_EXEFILE="${OPTARG}"
        ;;
    o)  OPT_OUTPUT="${OPTARG}"
        ;;
    v)  OPT_VERBOSE="verbose"
        ;;
    esac
done

if [ -z "${OPT_COREFILE}" ]; then
    show_help
    exit 1
fi

if [ ! -f "${OPT_COREFILE}" ]; then
    echo "Core dump file not found '${OPT_COREFILE}'"
    exit 1
fi

if [ ! -z "${OPT_OUTPUT}" ]; then
    OPT_OUTPUT="${OPT_OUTPUT%%/}/"
fi

if [ ! -d "${OPT_OUTPUT}" ]; then
    echo "Ouput path not found '${OPT_OUTPUT}'"
    exit 1
fi

if [ -z "${OPT_EXEFILE}" ]; then
    if ! [ -x "$(command -v file)" ]; then
        echo "Missing 'file' command. Install file package or specify -e argument"
    else      
        echo "Missing executable file argument, trying to read executable file path from core dump file"
        OPT_EXEFILE=$(file ${OPT_COREFILE}  | sed "s/.*execfn: '\([^']*\)',/\1/" | awk '{print $1}')

        if [ ! -x "${OPT_EXEFILE}" ]; then
            if [ -x "$(command -v eu-readelf)" ]; then
                echo "Executable file not found '${OPT_EXEFILE}'. Trying to get it with eu-readelf from elfutils"
                OPT_EXEFILE=$(eu-readelf -a ${OPT_COREFILE}  | grep psargs | awk '{print $2}')
            else
                echo "Missing 'eu-readelf' command. Install elfutils package or specify -e argument"
            fi

        fi
    fi

fi





if [ ! -x "${OPT_EXEFILE}" ]; then
    echo "Executable file not found '${OPT_EXEFILE}'"
    exit 1
fi

echo -e "Core Dump: '${OPT_COREFILE}'\nExecutable '${OPT_EXEFILE}'"

libs=""
if ! [ -x "$(command -v ldd)" ]; then
    echo "'ldd' command not found. Package will miss libraries loaded by ${OPT_EXEFILE}. It can be installed with libc-bin (or musl-utils on Alpine) package."
else
    libs=$(
        ldd ${OPT_EXEFILE} |
        awk '
            /=> \// {print $3}
            ! /=>/ {print $1}
        '
        )
fi

dynlibs=""
if ! [ -x "$(command -v readelf)" ]; then
    echo "'readelf' command not found. Package will miss libraries read from ${OPT_COREFILE}. It can be installed with binutils package."
else
    dynlibs=$(readelf -Wn ${OPT_COREFILE} | grep / | grep "\.so" | uniq | awk '{ if(system("[ -f " $1 " ]") == 0) { print $1} }')
fi
# exit

for lib in $libs
do
    if [ -f "$lib" ]; then
        [ -z "${OPT_VERBOSE}" ] || echo "LIB:    " $lib

        depenencies+=("$lib")
    fi
done

for lib in $dynlibs
do
    if [ -f "$lib" ]; then
        [ -z "${OPT_VERBOSE}" ] || echo "DYNLIB: " $lib
        depenencies+=("$lib")
    fi
done


depenencies+=(${OPT_COREFILE})
depenencies+=(${OPT_EXEFILE})

OUTPUT_FILENAME="${OPT_OUTPUT}$(basename ${OPT_COREFILE})-all.tar.xz"

echo "Compressing dependencies into archive '${OUTPUT_FILENAME}'"

printf '%s\n' "${depenencies[@]}" | tar -cah -T- -f ${OUTPUT_FILENAME}

if [ $? -eq 0 ]; then
    echo "done"
else
    echo "error"
fi