#!/usr/bin/env bash
set -xe

#
# run-test-command-with-timeout.sh <test_command> <max_duration> <max_tries> <file_for_output_prefix>
#

if [ -n "$1" ]; then
    test_command="$1"
else
    echo "The 1st parameter (test_command) is mandatory"
fi

if [ -n "$2" ]; then
    max_duration="$2"
else
    echo "The 2nd parameter (max_duration) is mandatory"
fi

if [ -n "$3" ]; then
    max_tries="$3"
else
    echo "The 3rd parameter (max_tries) is mandatory"
fi

if [ -n "$4" ]; then
    file_for_output_prefix="$4"
else
    echo "The 4th parameter (file_for_output_prefix) is mandatory"
fi

try_count=0
while [[ $try_count -lt $max_tries ]]; do
    ((++try_count))
    echo "Running $test_command (try $try_count out of $max_tries) ..."
    set -x
    # shellcheck disable=SC2086
    timeout $max_duration $test_command 2>&1 | tee "${file_for_output_prefix}_try_${try_count}.txt"
    exit_code=${PIPESTATUS[0]}
    set -xe
    if [ "$exit_code" -eq "0" ]; then
        echo "$test_command (try $try_count out of $max_tries) finished successfully"
        break
    fi

    # timeout returns 124 when the time limit is reached
    if [ "$exit_code" -eq "124" ]; then
        echo "$test_command (try $try_count out of $max_tries) timed out"
        continue
    fi

    echo "$test_command (try $try_count out of $max_tries) exited with an error code $exit_code"
    exit $exit_code
done
