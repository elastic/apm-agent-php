#!/usr/bin/env bash
set -xe

#
# run-test-command-with-timeout.sh <testCommand> <maxDuration> <maxTries>
#

if [ -n "$1" ]; then
    testCommand="$1"
else
    echo "The 1st parameter (testCommand) is mandatory"
fi

if [ -n "$2" ]; then
    maxDuration="$2"
else
    echo "The 2nd parameter (maxDuration) is mandatory"
fi

if [ -n "$3" ]; then
    maxTries="$3"
else
    echo "The 3rd parameter (maxTries) is mandatory"
fi

tryCount=0
while [[ $tryCount -le $maxTries ]]; do
    ++tryCount
    echo "Running $testCommand (try $tryCount out of $maxTries) ..."
    set -x
    # shellcheck disable=SC2086
    timeout $maxDuration $testCommand 2>&1 | tee /app/build/component-test_output_try_$tryCount.txt
    exitCode=$?
    set -xe
    if [ $exitCode -eq 0 ]; then
        break
    fi

    # timeout returns 124 when the time limit is reached
    if [ $exitCode -eq 124 ]; then
        echo "$testCommand (try $tryCount out of $maxTries) timed out"
        continue
    fi

    exit $exitCode
done
