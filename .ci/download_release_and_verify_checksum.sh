#!/usr/bin/env bash

set -xeo pipefail

release_tag="${1}"
original_artifacts_location="${2}"
downloaded_artifacts_location="./github"

echo "Downloading artifacts to ${downloaded_artifacts_location} ..."
mkdir -p "${downloaded_artifacts_location}"
pushd "${downloaded_artifacts_location}"
#
# Sergey Kleyman:
# 		Replaced target repo for publishing release to my fork (SergeyKleyman) to avoid noise while testing changes to release CI pipeline.
#		This change should be kept in a temporary (for tests only) PR
#		and IT SHOULD NEVER BE MERGED TO ANY "RELEASABLE" BRANCHES.
#
gh release download "${release_tag}" --repo "SergeyKleyman/apm-agent-php"
popd

ls -l "${original_artifacts_location}"
ls -l "${downloaded_artifacts_location}"

echo 'Verifying that downloaded artifacts pass the downloaded checksums...'
sha512sum --check *.sha512

sort "${original_artifacts_location}"/*.sha512 > original_artifacts.sha512
sort "${downloaded_artifacts_location}"/*.sha512 > downloaded_artifacts.sha512
cat original_artifacts.sha512
cat downloaded_artifacts.sha512

echo 'Verifying that original and downloaded artifacts have the same checksums...'
diff original_artifacts.sha512 downloaded_artifacts.sha512 || exit 1
