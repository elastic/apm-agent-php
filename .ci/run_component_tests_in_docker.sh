#!/usr/bin/env bash
set -xe

outputDir=/app/build/
mkdir -p "${outputDir}"

function startSyslog () {
    if which syslogd; then
        syslogd
    else
        if which rsyslogd; then
            rsyslogd
        else
            echo 'syslog is not installed'
            exit 1
        fi
    fi
}

function copySyslogFileAndPrintTheLastOne () {
    local possibleSyslogFiles=(/var/log/messages /var/log/syslog)
    for syslogFile in "${possibleSyslogFiles[@]}"
    do
        if [[ -f "${syslogFile}" ]]; then
            cp "${syslogFile}"* ${outputDir}
            echo "syslog files (${syslogFile}*) copied to ${outputDir}"
            break
        fi
    done
}

function printInfoAboutEnvironment () {
    echo 'PHP version:'
    php -v
    echo 'Installed PHP extensions:'
    php -m
    echo 'Set environment variables:'
    env | grep ELASTIC || true
}

function runComponentTests () {
    local composerRunScriptArgs=${COMPONENT_TEST_SCRIPT}

    if [ "${COMPONENT_TEST_SCRIPT}" != "run_component_tests" ] && [ -n "${ELASTIC_APM_PHP_TESTS_GROUP}" ]; then
        composerRunScriptArgs="${composerRunScriptArgs} --group ${ELASTIC_APM_PHP_TESTS_GROUP}"
    fi
    local composerCommand="composer run-script -- ${composerRunScriptArgs}"

    local initialTimeoutInMinutes=15
    local initialTimeoutInSeconds=$((initialTimeoutInMinutes*60))
    local run_command_with_timeout_and_retries_args=(--retry-on-error=no)
    run_command_with_timeout_and_retries_args=(--timeout="${initialTimeoutInSeconds}" "${run_command_with_timeout_and_retries_args[@]}")
    run_command_with_timeout_and_retries_args=(--max-tries=3 "${run_command_with_timeout_and_retries_args[@]}")
    run_command_with_timeout_and_retries_args=(--increase-timeout-exponentially=yes "${run_command_with_timeout_and_retries_args[@]}")

    set +e
    # shellcheck disable=SC2086 # composerCommand is not wrapped in quotes on purpose because it might contained multiple space separated strings
    .ci/run_command_with_timeout_and_retries.sh "${run_command_with_timeout_and_retries_args[@]}" -- ${composerCommand}
    local composerCommandExitCode=$?
    set -e

    if [ ${composerCommandExitCode} -ne 0 ]; then
        echo "${composerCommand} exited with an error code ${composerCommandExitCode}"
        copySyslogFileAndPrintTheLastOne
        exit ${composerCommandExitCode}
    fi
}


function main () {
    startSyslog

    export COMPONENT_TEST_SCRIPT="${COMPONENT_TEST_SCRIPT:-run_component_tests}"

    # Disable Elastic APM for any process outside the component tests to prevent noise in the logs
    export ELASTIC_APM_ENABLED=false

    # Install 3rd party dependencies
    composer install

    printInfoAboutEnvironment

    runComponentTests
}

main "$@"
