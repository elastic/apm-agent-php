#!/usr/bin/env bash
set -xe

if [ -n "$1" ]; then
    COMPONENT_TEST_SCRIPT="$1"
else
    COMPONENT_TEST_SCRIPT="${COMPONENT_TEST_SCRIPT:-run_component_tests}"
fi

# Disable Elastic APM for any process outside the component tests to prevent noise in the logs
export ELASTIC_APM_ENABLED=false

# Install 3rd party dependencies
composer install

# Start syslog
if which syslogd; then
    syslogd
else
    if which rsyslogd; then
        /usr/sbin/rsyslogd
    else
        echo 'syslog is not installed'
        exit 1
    fi
fi

# Run component tests
mkdir -p /app/build/
testCommand="composer run-script ${COMPONENT_TEST_SCRIPT}"
this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
# run-test-command-with-timeout.sh <test_command> <max_duration> <max_tries> <file_for_output_prefix>
"${this_script_dir}/run-test-command-with-timeout.sh" "${testCommand}" 30m 3 /app/build/component-test_output
