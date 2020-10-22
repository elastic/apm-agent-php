#!/usr/bin/env bash

LOOPS=${1:-50}
for (( c=1; c<=LOOPS; c++ ))
do  
    echo "Loop $c"
    make -f .ci/Makefile test
    make -f .ci/Makefile composer
done