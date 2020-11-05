#!/usr/bin/env bash
set -xe

## Location for the generated files
mkdir -p /app/build

cd /app/src/ext/unit_tests
cmake .
make
set +e
make test
## Save errorlevel to be reported later on
ret=$?

## Manipulate JUnit report without multiple testsuites entries.
for file in /app/build/*-unit-tests-junit.xml; do
    sed -i.bck ':begin;$!N;s#</testsuites>\n<testsuites>##;tbegin;P;D' "${file}"
done

## Return the error if any
if [ $ret -ne 0 ] ; then
    exit 1
fi
