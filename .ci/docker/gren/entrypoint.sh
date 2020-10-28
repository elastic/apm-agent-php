#!/usr/bin/env bash
set -exo pipefail

/usr/local/bin/gren changelog \
        --token="${GITHUB_TOKEN}" \
        --tags="current..${PREVIOUS_TAG}" \
        --generate \
        --override \
        --config .ci/.grenrc.js \
        --changelog-filename="CHANGELOG-RELEASE.asciidoc"

echo 'Force version in the generated changelog with the current release'
sed -i.bck "s/${PREVIOUS_TAG}/${TAG_NAME}/g" CHANGELOG-RELEASE.asciidoc

echo 'Aggregate generated release notes'
previousReleasesLine=$(grep -n -m 1 "CHANGELOG_AUTOMATION_KEYWORD" CHANGELOG.asciidoc | cut -f1 -d:)
{
  sed '/CHANGELOG_AUTOMATION_KEYWORD/q' CHANGELOG.asciidoc
  echo '' 
  cat CHANGELOG-RELEASE.asciidoc
  tail -n +$(( previousReleasesLine + 1 )) CHANGELOG.asciidoc
} > CHANGELOG.asciidoc.new
mv CHANGELOG.asciidoc.new CHANGELOG.asciidoc
rm CHANGELOG-RELEASE.asciidoc
