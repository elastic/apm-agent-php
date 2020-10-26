#!/usr/bin/env bash

LOOPS=${1:-50}
DOCKERFILE=${2:-Dockerfile}
PHP_VERSION=${3:-7.2}
for (( c=1; c<=LOOPS; c++ ))
do  
    echo "Loop $c"
    PHP_VERSION=${PHP_VERSION} DOCKERFILE=${DOCKERFILE} make -f .ci/Makefile test
    PHP_VERSION=${PHP_VERSION} DOCKERFILE=${DOCKERFILE} make -f .ci/Makefile composer

    ## TODO: rename test report files to include the iteration name.
done