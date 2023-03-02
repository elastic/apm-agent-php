#!/usr/bin/env bash
##  This script runs the release given the different environment variables
##    dry_run
##
##  It relies on the .buildkite/hooks/pre-command so the Vault and other tooling
##  are prepared automatically by buildkite.
##
set -eo pipefail

set +x
echo "--- Sign the binaries"
if [[ "$dry_run" == "true" ]] ; then
  echo "run the signing job 'elastic+unified-release+master+sign-artifacts-with-gpg'" | tee -a release.txt
else
  echo 'TBD' | tee release.txt
fi

