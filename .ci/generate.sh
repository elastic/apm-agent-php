#!/usr/bin/env bash

set -exo pipefail

HYPHEN="-"
MODULES_DIR=/app/src/ext/modules
NAME=elastic_apm
## Prepare context where to copy the previous generated so files
GENERATED=$(mktemp -d /tmp/dir-XXXX)
SEARCH="${MODULES_DIR}/*${HYPHEN}*.so"
if ls "${SEARCH}" 1> /dev/null 2>&1; then
  cp -f "${SEARCH}" "${GENERATED}" || true
fi

## Generate so file
make clean
make

## Fetch PHP api version to be added to the so file that has been generated
PHP_API=$(php -i | grep -i 'PHP API' | sed -e 's#.* =>##g' | awk '{print $1}')

## Rename so file with the PHP api
mv "${MODULES_DIR}/${NAME}.so" "${MODULES_DIR}/${NAME}${HYPHEN}${PHP_API}.so"

## Remove la files 
find "${MODULES_DIR}" -name "*.la" -print0 | xargs -0 rm -f

## Restore previous so files.
if ls "${SEARCH}" 1> /dev/null 2>&1; then
  cp -f "${SEARCH}" ${MODULES_DIR}/
fi
