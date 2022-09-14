#!/usr/bin/env bash
set -xeo pipefail

release_tag="${1}"
original_artifacts_location="${2}"
downloaded_artifacts_location="./github"

echo "Downloading artifacts for tag \'${release_tag}\' to \'${downloaded_artifacts_location}\' ..."
mkdir -p "${downloaded_artifacts_location}"
pushd "${downloaded_artifacts_location}"
gh release download "${release_tag}"
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
