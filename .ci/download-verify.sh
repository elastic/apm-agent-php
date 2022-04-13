#!/usr/bin/env bash
set -eo pipefail
RELEASE_ID=${1}
TARGET=${2}
GITHUB=download

echo 'download artifacts'
mkdir $GITHUB || true
cd $GITHUB
## TODO: wait for https://github.com/cli/cli/pull/5442
## gh release download --release-id ${RELEASE_ID}
gh release download "$TAG_NAME" --repo v1v/apm-agent-php
cd ..

echo 'calculate the sha512'
sort "$GITHUB/*.sha512" > github.sha512
sort "$TARGET/*.sha512" > target.sha512

echo 'verify sha512 are valid'
diff github.sha512 target.sha512
