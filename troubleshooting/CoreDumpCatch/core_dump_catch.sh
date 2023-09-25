#!/bin/sh
# Example how to set it up:
# echo -e "|/usr/local/bin/core_dump_catch.sh %p %P %i %s %h %t %E %e" >/proc/sys/kernel/core_pattern

exec 100<&0
exec 0<&-

OUTPUT_DIRECTORY=/tmp

PID=$1
PIDD=$2
TID=$3
SIGNAL=$4
HOSTNAME=$5
TIME=$6
PATHNAME=$7
EXECUTABLE=$8


CMDLINE=`cat /proc/${PID}/cmdline`
CWD=`readlink -e /proc/${PID}/cwd`

FILENAMEBASE=${OUTPUT_DIRECTORY}/`date  '+%Y%m%d_%H%M%S' -d @${TIME}`_${EXECUTABLE}_${SIGNAL}_${PID}_${TID}_${TIME}

REALPATH=`echo "${PATHNAME}" | sed -r 's/[!]+/\//g'`

echo "Cmd: '${CMDLINE}'" >${FILENAMEBASE}.log
echo "Pid: ${PID}/${PIDD}" >>${FILENAMEBASE}.log
echo "Tid: ${TID}" >>${FILENAMEBASE}.log
echo "Signal: ${SIGNAL}" >>${FILENAMEBASE}.log
echo "Hostname: '${HOSTNAME}'" >>${FILENAMEBASE}.log
echo "Time: '${TIME}'" >>${FILENAMEBASE}.log
echo "PathName: '${PATHNAME}'" >>${FILENAMEBASE}.log
echo "RealPath: '${REALPATH}'" >>${FILENAMEBASE}.log
echo "Executable: '${EXECUTABLE}'" >>${FILENAMEBASE}.log
echo "CWD: '${CWD}'" >>${FILENAMEBASE}.log
echo "DumpArgs: $0"  >>${FILENAMEBASE}.log

cat </proc/self/fd/100 >${FILENAMEBASE}.core

cp -f ${REALPATH} ${FILENAMEBASE}_${EXECUTABLE}
