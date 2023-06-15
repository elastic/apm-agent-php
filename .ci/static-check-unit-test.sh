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

phpt_timeout_seconds=60
run_phpt_test_with_timeout_and_retries_args=(--retry-on-error=no)
run_phpt_test_with_timeout_and_retries_args=(--max-tries=1 "${run_phpt_test_with_timeout_and_retries_args[@]}")
run_phpt_test_with_timeout_and_retries_args=(--timeout=${phpt_timeout_seconds} "${run_phpt_test_with_timeout_and_retries_args[@]}")

PHP_EXECUTABLE=$(which php)

RUN_TESTS=$APP_FOLDER/src/ext/tests/run-tests.php
AGENT_EXTENSION_DIR=$APP_FOLDER/src/ext/modules
AGENT_EXTENSION=$AGENT_EXTENSION_DIR/elastic_apm.so

# ## This runs PHPT
# # Disable agent for auxiliary PHP processes to reduce noise in logs
export ELASTIC_APM_ENABLED=true
for phptFile in ./tests/*.phpt; do
    msg="Running tests in \`${phptFile}' ..."
    echo "${msg}"
    this_script_name="$( basename "${BASH_SOURCE[0]}" )"
    logger -t "${this_script_name}" "${msg}"

    # Disable exit-on-error
    # set +e
    TESTS="--show-all ${phptFile}"

    INI_FILE=`php -d 'display_errors=stderr' -r 'echo php_ini_loaded_file();' 2> /dev/null`

    if test "$INI_FILE"; then
        egrep -h -v $PHP_DEPRECATED_DIRECTIVES_REGEX "$INI_FILE" > /tmp/tmp-php.ini
    else
        echo > /tmp/tmp-php.ini
    fi

    TEST_PHP_SRCDIR=$APP_FOLDER/src/ext TEST_PHP_EXECUTABLE=$PHP_EXECUTABLE "${thisScriptDir}/run_command_with_timeout_and_retries.sh" "${run_command_with_timeout_and_retries_args[@]}" -- $PHP_EXECUTABLE -n -c /tmp/tmp-php.ini $PHP_TEST_SETTINGS $RUN_TESTS -n -c /tmp/tmp-php.ini -d extension_dir=$AGENT_EXTENSION_DIR -d extension=$AGENT_EXTENSION  $PHP_TEST_SHARED_EXTENSIONS $TESTS
    exitCode=$?
    rm /tmp/tmp-php.ini


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
composer run-script -- run_unit_tests_custom_config -c "${phpUnitConfigFile}"
ls -l ./build/unit-tests-phpunit-junit.xml

# Generate junit output for phpstan
composer phpstan-junit-report-for-ci

scriptFinishedSuccessfully=true