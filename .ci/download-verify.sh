#!/usr/bin/env bash
set -eo pipefail
RELEASE_ID=${1}
TARGET=${2}
GITHUB=github

echo 'download artifacts'
mkdir $GITHUB || true
cd $GITHUB
## TODO: wait for https://github.com/cli/cli/pull/5442
## gh release download --release-id ${RELEASE_ID}
#
# Sergey Kleyman:
# 		Replaced target repo for publishing release to my fork (SergeyKleyman) to avoid noise while testing changes to release CI pipeline.
#		This change should be kept in a temporary (for tests only) PR
#		and IT SHOULD NEVER BE MERGED TO ANY "RELEASABLE" BRANCHES.
#
gh release download "$TAG_NAME" --repo "SergeyKleyman/apm-agent-php"
cd ..

echo 'debug github artifacts'
ls -l $GITHUB

echo 'debug target artifacts'
ls -l $TARGET

echo 'calculate the sha512'
sort $GITHUB/*.sha512 > github.sha512
sort $TARGET/*.sha512 > target.sha512

echo 'verify sha512 are valid'
diff github.sha512 target.sha512 || exit 1

echo 'debug: sha512 for the github artifacts'
cat github.sha512

echo 'debug: sha512 for the signed artifacts'
cat target.sha512
