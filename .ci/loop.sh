#!/usr/bin/env bash

LOOPS=${1:-50}
DOCKERFILE=${2:-Dockerfile}
PHP_VERSION=${3:-7.2}

OUTPUT_FOLDER="build/loop-$DOCKERFILE-$PHP_VERSION"
TEST_REPORT_TEST="junit.xml"
TEST_REPORT_COMPOSER="build/component-tests-phpunit-junit.xml"
mkdir -p "${OUTPUT_FOLDER}" || true

for (( c=1; c<=LOOPS; c++ ))
do  
    echo "Loop $c"
    OUTPUT_FILE="$OUTPUT_FOLDER/$c.txt"
    PHP_VERSION=${PHP_VERSION} DOCKERFILE=${DOCKERFILE} make -f .ci/Makefile static-check-unit-test | tee "$OUTPUT_FILE"
    PHP_VERSION=${PHP_VERSION} DOCKERFILE=${DOCKERFILE} make -f .ci/Makefile component-test | tee -a "$OUTPUT_FILE"

    ## Store test results with the iteration
    if [ -e $TEST_REPORT_TEST ] ; then
        mv $TEST_REPORT_TEST "${OUTPUT_FOLDER}/junit-test-$c.xml"
    fi

    ## Store composer test results with the iteration
    if [ -e $TEST_REPORT_COMPOSER ] ; then
        ## Let's copy since mv does not work when reusing the workspace for some required permissions
        cp $TEST_REPORT_COMPOSER "${OUTPUT_FOLDER}/junit-composer-$c.xml"
    fi
done