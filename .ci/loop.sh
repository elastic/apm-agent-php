#!/usr/bin/env bash

LOOPS=${1:-50}
DOCKERFILE=${2:-Dockerfile}
PHP_VERSION=${3:-7.2}

OUTPUT_FOLDER=build
TEST_REPORT_TEST="junit.xml"
TEST_REPORT_COMPOSER="_GENERATED/COMPONENT_TESTS/log_as_junit.xml"
mkdir -p $OUTPUT_FOLDER || true
for (( c=1; c<=LOOPS; c++ ))
do  
    echo "Loop $c"
    PHP_VERSION=${PHP_VERSION} DOCKERFILE=${DOCKERFILE} make -f .ci/Makefile test | tee "$OUTPUT_FOLDER/$c.txt"
    PHP_VERSION=${PHP_VERSION} DOCKERFILE=${DOCKERFILE} make -f .ci/Makefile composer | tee -a "$OUTPUT_FOLDER/$c.txt"

    ## Store test results with the iteration
    if [ -e $TEST_REPORT_TEST ] ; then
        mv $TEST_REPORT_TEST "$TEST_REPORT_TEST.$c.xml"
    fi

    ## Store composer test results with the iteration
    if [ -e $TEST_REPORT_COMPOSER ] ; then
        mv $TEST_REPORT_COMPOSER "$TEST_REPORT_COMPOSER.$c.xml"
    fi
done