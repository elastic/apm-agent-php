#!/usr/bin/env bash
set -exo pipefail

GENERATED_RELEASE_FILE=build/CHANGELOG-RELEASE.asciidoc
GENERATED_CHANGELOG=build/CHANGELOG.asciidoc
CHANGELOG=CHANGELOG.asciidoc

# Where the files will be generated
mkdir -p build || true

/usr/local/bin/gren changelog \
        --token="${GITHUB_TOKEN}" \
        --tags="ci-tag..${PREVIOUS_TAG}" \
        --generate \
        --override \
        --config .ci/.grenrc.js \
        --changelog-filename="${GENERATED_RELEASE_FILE}"

echo 'Aggregate generated release notes'
previousReleasesLine=$(grep -n -m 1 "CHANGELOG_AUTOMATION_KEYWORD" ${CHANGELOG} | cut -f1 -d:)
{
  sed '/CHANGELOG_AUTOMATION_KEYWORD/q' ${CHANGELOG}
  echo '' 
  sed "s/${PREVIOUS_TAG}/${TAG_NAME}/g" "${GENERATED_RELEASE_FILE}"
  tail -n +$(( previousReleasesLine + 1 )) ${CHANGELOG}
} > ${GENERATED_CHANGELOG}
cp ${GENERATED_CHANGELOG} ${CHANGELOG}
