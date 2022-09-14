#!/usr/bin/env bash
set -xe

#
# run-test-command-with-timeout.sh --timeout=<number of seconds> --max-tries=<number> --sleep-time-before-retry=<number of seconds> --wait-time-before-retry=<number of seconds> --retry-on-error -- <command_to_run>
#

function parse_command_line_arguments () {
    for arg in "$@"; do
      shift
      case "$arg" in
        '--help')   set -- "$@" '-h'   ;;
        '--number') set -- "$@" '-n'   ;;
        '--rest')   set -- "$@" '-r'   ;;
        '--ws')     set -- "$@" '-w'   ;;
        *)          set -- "$@" "$arg" ;;
      esac
    done

    # NOTE: This requires GNU getopt.
    # On Mac OS X and FreeBSD, you have to install this separately; see below.
    TEMP=$(getopt --long timeout:,debugfile:,minheap:,maxheap: \
                  -n 'javawrap' -- "$@")

    if [ $? != 0 ] ; then echo "Terminating..." >&2 ; exit 1 ; fi

    # Note the quotes around '$TEMP': they are essential!
    eval set -- "$TEMP"

    VERBOSE=false
    DEBUG=false
    MEMORY=
    DEBUGFILE=
    JAVA_MISC_OPT=
    while true; do
      case "$1" in
        -v | --verbose ) VERBOSE=true; shift ;;
        -d | --debug ) DEBUG=true; shift ;;
        -m | --memory ) MEMORY="$2"; shift 2 ;;
        --debugfile ) DEBUGFILE="$2"; shift 2 ;;
        --minheap )
          JAVA_MISC_OPT="$JAVA_MISC_OPT -XX:MinHeapFreeRatio=$2"; shift 2 ;;
        --maxheap )
          JAVA_MISC_OPT="$JAVA_MISC_OPT -XX:MaxHeapFreeRatio=$2"; shift 2 ;;
        -- ) shift; break ;;
        * ) break ;;
      esac
    done
}

if [ -n "$1" ]; then
    timeout="$1"
else
    echo "The 1st parameter (timeout in seconds) is mandatory"
fi

if [ -n "$2" ]; then
    max_tries="$2"
else
    echo "The 2nd parameter (max_tries) is mandatory"
fi

if [ -n "$3" ]; then
    retry_on_error="$3"
else
    echo "The 3rd parameter (retry_on_error: retry_on_error or no_retry_on_error) is mandatory"
fi

if [ -n "$4" ]; then
    command_to_run="${*:4}"
else
    echo "The 4th (and the rest) parameter(s) (command_to_run) is mandatory"
fi

try_count=0
while [[ ${try_count} -lt ${max_tries} ]]; do
    ((++try_count))
    echo "Running ${command_to_run} (try ${try_count} out of ${max_tries}) ..."
    set +e
    # shellcheck disable=SC2086
    timeout "${timeout}" ${command_to_run}
    exit_code=${PIPESTATUS[0]}
    set -e
    if [ "${exit_code}" -eq "0" ]; then
        echo "${command_to_run} (try ${try_count} out of ${max_tries}) finished successfully"
        break
    fi

    # timeout returns 124 when the time limit is reached
    if [ "${exit_code}" -eq "124" ]; then
        echo "${command_to_run} (try ${try_count} out of ${max_tries}) timed out"
        continue
    fi

    echo "${command_to_run} (try ${try_count} out of max ${max_tries}) exited with an error code ${exit_code}"
    if [ "${retry_on_error}" = "retry_on_error" ]; then
        continue
    fi
    exit "${exit_code}"
done
