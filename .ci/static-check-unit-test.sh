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

echo "Starting .phpt tests at $(date +"%Y-%m-%d %H:%M:%S")"
phpt_timeout_seconds=${ELASTIC_APM_PHP_TESTS_PHPT_TIMEOUT_SECONDS:-60}
run_phpt_test_with_timeout_and_retries_args=(--retry-on-error=no)
run_phpt_test_with_timeout_and_retries_args=(--max-tries=1 "${run_phpt_test_with_timeout_and_retries_args[@]}")
run_phpt_test_with_timeout_and_retries_args=(--timeout=${phpt_timeout_seconds} "${run_phpt_test_with_timeout_and_retries_args[@]}")

## This make runs PHPT
# Disable agent for auxiliary PHP processes to reduce noise in logs
export ELASTIC_APM_ENABLED=false
for phptFile in ./tests/*.phpt; do
    phptFileName="$(basename -- ${phptFile})"

#    if [[ "${phptFileName}" == "opcache_preload_detection.phpt" ]]; then
#        echo "Skipping tests in \`${phptFile}' ..."
#        continue
#    fi

#    if [[ "${phptFileName}" == "opcache_preload_detection_double.phpt" ]]; then
#        echo "Skipping tests in \`${phptFile}' ..."
#        continue
#    fi

    msg="Running tests in \`${phptFile}' ..."
    echo "${msg}"
    this_script_name="$( basename "${BASH_SOURCE[0]}" )"
    logger -t "${this_script_name}" "${msg}"

    # Disable exit-on-error
    set +e
    "${thisScriptDir}/run_command_with_timeout_and_retries.sh" "${run_command_with_timeout_and_retries_args[@]}" -- make test TESTS="--show-all ${phptFile}"
    exitCode=$?

    if [ ${exitCode} -ne 0 ] ; then
        echo "Tests in \`${phptFile}' failed"
        phptFileName="${phptFile%.phpt}"
        cat "${phptFileName}.log"
        cat "${phptFileName}.out"
        exit 1
    fi

    # Re-enable exit-on-error
    set -e
done
echo "Finished .phpt tests at $(date +"%Y-%m-%d %H:%M:%S")"

echo "Starting native part unit tests (using cmocka) at $(date +"%Y-%m-%d %H:%M:%S")"
# Disable exit-on-error
set +e
## Run cmocka tests
function buildAndRunUnitTests () {
    pushd /app/src/ext/unit_tests
    for buildType in Debug Release
    do
        cmake -DCMAKE_BUILD_TYPE=${buildType} .
        make
        ./unit_tests
        unitTestsExitCode=$?
        if [ ${unitTestsExitCode} -ne 0 ] ; then
            popd
            return ${unitTestsExitCode}
        fi
    done
    popd
}
buildAndRunUnitTests
## Save errorlevel to be reported later on
ret=$?
echo "Finished native part unit tests (using cmocka) at $(date +"%Y-%m-%d %H:%M:%S")"

## Manipulate JUnit report without multiple testsuites entries.
for file in "${BUILD_FOLDER}"/*-unit-tests-junit.xml; do
    sed -i.bck ':begin;$!N;s#</testsuites>\n<testsuites>##;tbegin;P;D' "${file}"
done

## Return the error if any
if [ $ret -ne 0 ] ; then
    exit 1
fi

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
phpUnitConfigFile=$(php ./tests/ElasticApmTests/Util/runSelectPhpUnitConfigFile.php --tests-type=unit)
echo "Starting PHP part unit tests (using PHPUnit) at $(date +"%Y-%m-%d %H:%M:%S")"
composer run-script -- run_unit_tests_custom_config -c "${phpUnitConfigFile}"
echo "Finished PHP part unit tests (using PHPUnit) at $(date +"%Y-%m-%d %H:%M:%S")"
ls -l ./build/unit-tests-phpunit-junit.xml

# Generate junit output for phpstan
composer phpstan-junit-report-for-ci

scriptFinishedSuccessfully=true