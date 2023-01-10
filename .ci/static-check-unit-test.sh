#!/usr/bin/env bash
set -xe

## Location for the generated test report files
BUILD_FOLDER=/app/build
mkdir -p ${BUILD_FOLDER}

function ensureSyslogIsRunning () {
    if ps -ef | grep -v 'grep' | grep -q 'syslogd' ; then
        echo 'Syslog is already started.'
        return
    fi

    if which rsyslogd; then
        grep /var/log/messages /etc/rsyslog.conf
        sed -i '/\/var\/log\/messages/s/messages/syslog/' /etc/rsyslog.conf
        rsyslogd
    else
        if which syslogd; then
            syslogd
        else
            echo 'syslog is not installed'
            exit 1
        fi
    fi
}

function copySyslogFileAndPrintTheLastOne () {
    ls -l /var/log/
    local possibleSyslogFiles=(/var/log/messages /var/log/syslog)
    for syslogFile in "${possibleSyslogFiles[@]}"
    do
        if [[ -f "${syslogFile}" ]]; then
            local syslogCopyDir="${BUILD_FOLDER}/syslog"
            mkdir -p "${syslogCopyDir}"
            cp "${syslogFile}"* ${syslogCopyDir}
            echo "syslog files (${syslogFile}*) copied to ${syslogCopyDir}"
        fi
    done

    for syslogFile in "${possibleSyslogFiles[@]}"
    do
        if [[ -f "${syslogFile}" ]]; then
            echo "Content of the most recent syslog file (${syslogFile}):"
            cat "${syslogFile}"
        fi
    done
}

function onExit () {
    copySyslogFileAndPrintTheLastOne
    chmod -R +r "${BUILD_FOLDER}"
}

trap onExit EXIT

ensureSyslogIsRunning

## This make runs PHPT
# Disable agent for auxiliary PHP processes to reduce noise in logs
export ELASTIC_APM_ENABLED=false
for phptFile in ./tests/*.phpt; do
    msg="Running tests in \`${phptFile}' ..."
    echo "${msg}"
    this_script_name="$( basename "${BASH_SOURCE[0]}" )"
    logger -t "${this_script_name}" "${msg}"

    # Disable exit-on-error
    set +e
    make test TESTS="--show-all ${phptFile}"
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

## Run cmocka tests
cd /app/src/ext/unit_tests
cmake .
make
set +e
./unit_tests

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

## Enable the error again
set -e

cd /app

echo 'Set Elastic related environment variables:'
env | grep ELASTIC || true

# Install 3rd party dependencies
composer install

# Run static_check_and_run_unit_tests
composer run-script static_check_and_run_unit_tests

# Generate junit output for phpstan
composer phpstan-junit-report-for-ci
