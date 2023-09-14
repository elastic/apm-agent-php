#!/usr/bin/env bash
set -xe

## Location for the generated test report files
APP_FOLDER=/app
BUILD_FOLDER="${APP_FOLDER}/build"
mkdir -p "${BUILD_FOLDER}"

thisScriptDir="$( dirname "${BASH_SOURCE[0]}" )"
thisScriptDir="$( realpath "${thisScriptDir}" )"
source "${thisScriptDir}/shared.sh"

function onScriptExit () {
    if [ -n "${scriptFinishedSuccessfully}" ] && [ "${scriptFinishedSuccessfully}" == "true" ] ; then
        local shouldPrintTheMostRecentSyslogFile=false
    else
        local shouldPrintTheMostRecentSyslogFile=true
    fi

    copySyslogFilesAndPrintTheMostRecentOne ${shouldPrintTheMostRecentSyslogFile}
    if [ -n "${CHOWN_RESULTS_UID}" ] && [ -n "${CHOWN_RESULTS_GID}" ]; then
        chown --recursive "${CHOWN_RESULTS_UID}:${CHOWN_RESULTS_GID}" "${APP_FOLDER}"
    fi
}

trap onScriptExit EXIT

ensureSyslogIsRunning

# Re-enable exit-on-error
set -e

cd /app

echo 'Set Elastic related environment variables:'
env | grep ELASTIC || true

# Install 3rd party dependencies
runPhpCoposerInstall

# Run static checks
composer run-script static_check

# Run unit tests
composerCommandToRunUnitTests=(composer run-script --)
phpUnitConfigFile=$(php ./tests/ElasticApmTests/Util/runSelectPhpUnitConfigFile.php --tests-type=unit)
composerCommandToRunUnitTests=("${composerCommand[@]}" run_unit_tests_custom_config -c "${phpUnitConfigFile})
if [ "${ELASTIC_APM_PHP_TESTS_AGENT_ENABLED_CONFIG_DEFAULT:?}" == "false" ]; then
    composerCommandToRunUnitTests=("${AgentEnabledConfigUnitTest[@]}" --filter "AgentEnabledConfigUnitTest")
fi
"${composerCommandToRunUnitTests[@]}"
ls -l ./build/unit-tests-phpunit-junit.xml

# Generate junit output for phpstan
composer phpstan-junit-report-for-ci

scriptFinishedSuccessfully=true