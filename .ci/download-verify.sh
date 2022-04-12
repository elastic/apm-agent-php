#!/usr/bin/env bash
set -eo pipefail
RELEASE_ID=${1}
TARGET=${2}
GITHUB=download

echo 'download artifacts'
mkdir $GITHUB || true
cd $GITHUB
## TODO: wait for https://github.com/cli/cli/pull/5442
gh release download --release-id ${RELEASE_ID}
cd ..

echo 'calculate the sha512'
cat "$GITHUB/*.sha512" | sort > github.sha512
cat "$TARGET/*.sha512" | sort > target.sha512

echo 'verify sha512 are valid'
diff github.sha512 target.sha512

