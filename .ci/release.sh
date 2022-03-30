#!/usr/bin/env bash
set -exo pipefail

USER=elastic
REPO=apm-agent-php

## Install tooling
go get github.com/github-release/github-release

## Create a formal release
github-release release \
    --user ${USER} \
    --repo ${REPO} \
    --tag "${TAG_NAME}" \
    --description "For more information, please see the [changelog](https://www.elastic.co/guide/en/apm/agent/php/current/release-notes.html)."

## Info
github-release info \
    --user ${USER} \
    --repo ${REPO} \
    --tag "${TAG_NAME}"

## Upload the distribution files
for package in build/packages/* ; do
  name=$(basename "${package}")
  github-release upload \
      --user ${USER} \
      --repo ${REPO} \
      --tag "${TAG_NAME}" \
      --name "${name}" \
      --file "${package}"
done

## Info
github-release info \
    --user ${USER} \
    --repo ${REPO} \
    --tag "${TAG_NAME}"
