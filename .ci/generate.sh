#!/usr/bin/env bash

set -exo pipefail

## Prepare context where to copy the previous generated so files
GENERATED=$(mktemp -d /tmp/dir-XXXX)
if ls /app/src/ext/modules/*.so 1> /dev/null 2>&1; then
  cp -rf /app/src/ext/modules/*.so "${GENERATED}" || true
fi

## Generate so file
make clean
make

## Fetch PHP api version to be added to the so file that has been generated
PHP_API=$(php -i | grep -i 'PHP API' | sed -e 's#.* =>##g' | awk '{print $1}')

## Rename so file with the PHP api
mv /app/src/ext/modules/elastic_apm.so /app/src/ext/modules/elastic_apm-"${PHP_API}".so

## Remove la files 
find /app/src/ext/modules -name "*.la" -print0 | xargs -0 rm -f

## Restore previous so files.
if ls "${GENERATED}"/*.so 1> /dev/null 2>&1; then
  cp "${GENERATED}"/*.so /app/src/ext/modules/
fi
