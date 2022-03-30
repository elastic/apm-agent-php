#!/usr/bin/env bash
set -exo pipefail

USER=elastic
REPO=apm-agent-php

## Install tooling
go get github.com/github-release/github-release

echo "INFO: Create GitHub release"
github-release release \
    --user ${USER} \
    --repo ${REPO} \
    --tag "${TAG_NAME}" \
    --description "For more information, please see the [changelog](https://www.elastic.co/guide/en/apm/agent/php/current/release-notes.html)."

echo "INFO: sleep since the release might not be available in GitHub yet"
sleep 5
echo "INFO: Gather release details"
github-release info \
    --user ${USER} \
    --repo ${REPO} \
    --tag "${TAG_NAME}"

echo "INFO: Upload the distribution files"
for package in build/packages/* ; do
  name=$(basename "${package}")
  github-release upload \
      --user ${USER} \
      --repo ${REPO} \
      --tag "${TAG_NAME}" \
      --name "${name}" \
      --file "${package}"
done

echo "INFO: Gather release details after uploading the distribution files"
github-release info \
    --user ${USER} \
    --repo ${REPO} \
    --tag "${TAG_NAME}"
