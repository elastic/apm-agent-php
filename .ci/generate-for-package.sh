#!/usr/bin/env bash

set -exo pipefail

HYPHEN="-"
MODULES_DIR=/app/src/ext/modules
NAME=elastic_apm
## Prepare context where to copy the previous generated so files
GENERATED_DIR=$(mktemp -d /tmp/dirXXXXXX)
find "${MODULES_DIR}" -name "*${HYPHEN}*.so" -exec cp {} ${GENERATED_DIR} \;
ls -l ${GENERATED_DIR}

## If alpine then add another suffix
if grep -q -i alpine /etc/os-release; then
  SUFFIX=${HYPHEN}alpine
fi

## Generate so file
make clean
make

## Fetch PHP api version to be added to the so file that has been generated
PHP_API=$(php -i | grep -i 'PHP API' | sed -e 's#.* =>##g' | awk '{print $1}')

## Rename so file with the PHP api
mv "${MODULES_DIR}/${NAME}.so" "${MODULES_DIR}/${NAME}${HYPHEN}${PHP_API}${SUFFIX}.so"

## Remove la files 
find "${MODULES_DIR}" -name "*.la" -print0 | xargs -0 rm -f

## Restore previous so files.
find "${GENERATED_DIR}" -name "*${HYPHEN}*.so" -exec cp -f {} ${MODULES_DIR}/ \;
