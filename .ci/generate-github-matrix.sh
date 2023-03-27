#!/usr/bin/env bash
set -e

echo "php-versions=$(jq -c .versions .ci/testing.json)" >> $GITHUB_OUTPUT
## gather the oldest and newest PHP versions to be used for testing purposes.
echo "php-versions-limits=$(jq -c .versions .ci/testing.json | jq -c '[.[0, -1]]')" >> $GITHUB_OUTPUT

echo "lifecycle-package=$(jq -c .lifecycle.package .ci/testing.json)" >> $GITHUB_OUTPUT
echo "lifecycle-group=$(jq -c .lifecycle.group .ci/testing.json)" >> $GITHUB_OUTPUT
echo "lifecycle-subgroup=$(jq -c .lifecycle.subgroup .ci/testing.json)" >> $GITHUB_OUTPUT
echo "lifecycle-testing=$(jq -c .lifecycle.testing .ci/testing.json)" >> $GITHUB_OUTPUT

echo "lifecycle-apache-package=$(jq -c .lifecycle_apache.package .ci/testing.json)" >> $GITHUB_OUTPUT
echo "lifecycle-apache-group=$(jq -c .lifecycle_apache.group .ci/testing.json)" >> $GITHUB_OUTPUT
echo "lifecycle-apache-subgroup=$(jq -c .lifecycle_apache.subgroup .ci/testing.json)" >> $GITHUB_OUTPUT
echo "lifecycle-apache-testing=$(jq -c .lifecycle_apache.testing .ci/testing.json)" >> $GITHUB_OUTPUT

echo "lifecycle-debug-package=$(jq -c .lifecycle_debug.package .ci/testing.json)" >> $GITHUB_OUTPUT
echo "lifecycle-debug-group=$(jq -c .lifecycle_debug.group .ci/testing.json)" >> $GITHUB_OUTPUT
echo "lifecycle-debug-subgroup=$(jq -c .lifecycle_debug.subgroup .ci/testing.json)" >> $GITHUB_OUTPUT
echo "lifecycle-debug-testing=$(jq -c .lifecycle_debug.testing .ci/testing.json)" >> $GITHUB_OUTPUT
