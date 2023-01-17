#!/usr/bin/env bash
set -xe

function printInfoAboutEnvironment () {
    echo 'PHP version:'
    php -v
    echo 'Installed PHP extensions:'
    php -m
    echo 'Set Elastic related environment variables:'
    env | grep ELASTIC || true
}

function runComponentTests () {
    local composerCommand=(composer run-script --)

    if [ -z "${ELASTIC_APM_PHP_TESTS_APP_CODE_HOST_KIND}" ] || [ "${ELASTIC_APM_PHP_TESTS_APP_CODE_HOST_KIND}" == "all" ]; then
        composerCommand=("${composerCommand[@]}" run_component_tests)
    else
        composerCommand=("${composerCommand[@]}" run_component_tests_configured)
    fi

    if [ -n "${ELASTIC_APM_PHP_TESTS_GROUP}" ] ; then
        composerCommand=("${composerCommand[@]}" --group "${ELASTIC_APM_PHP_TESTS_GROUP}")
    fi

    if [ -n "${ELASTIC_APM_PHP_TESTS_FILTER}" ] ; then
        composerCommand=("${composerCommand[@]}" --filter "${ELASTIC_APM_PHP_TESTS_FILTER}")
    fi

    local initialTimeoutInMinutes=30
    local initialTimeoutInSeconds=$((initialTimeoutInMinutes*60))
    local run_command_with_timeout_and_retries_args=(--retry-on-error=no)
    run_command_with_timeout_and_retries_args=(--timeout="${initialTimeoutInSeconds}" "${run_command_with_timeout_and_retries_args[@]}")
    run_command_with_timeout_and_retries_args=(--max-tries=3 "${run_command_with_timeout_and_retries_args[@]}")
    run_command_with_timeout_and_retries_args=(--increase-timeout-exponentially=yes "${run_command_with_timeout_and_retries_args[@]}")

    set +e
    # shellcheck disable=SC2086 # composerCommand is not wrapped in quotes on purpose because it might contained multiple space separated strings
    .ci/run_command_with_timeout_and_retries.sh "${run_command_with_timeout_and_retries_args[@]}" -- "${composerCommand[@]}"
    local composerCommandExitCode=$?
    set -e

    echo "${composerCommand[*]} exited with an error code ${composerCommandExitCode}"
    copySyslogFileAndPrintTheLastOne
    exit ${composerCommandExitCode}
}

function main () {
    thisScriptDir="$( dirname "${BASH_SOURCE[0]}" )"
    thisScriptDir="$( realpath "${thisScriptDir}" )"
    source "${thisScriptDir}/shared.sh"

    ensureSyslogIsRunning

    # Disable Elastic APM for any process outside the component tests to prevent noise in the logs
    export ELASTIC_APM_ENABLED=false

    # Install 3rd party dependencies
    composer install

    printInfoAboutEnvironment

    runComponentTests
}

main "$@"
