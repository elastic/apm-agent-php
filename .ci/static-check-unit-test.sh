#!/usr/bin/env bash
set -xe

## Location for the generated test report files
BUILD_FOLDER=/app/build
mkdir -p ${BUILD_FOLDER}

## This make runs PHPT
# Disable agent for auxiliary PHP processes to reduce noise in logs
export ELASTIC_APM_ENABLED=false
make test

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
