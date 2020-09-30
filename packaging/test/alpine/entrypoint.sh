#!/usr/bin/env sh
set -x

if [ "${TYPE}" = "release-github" ] ; then
    mkdir -p build/releases
    PACKAGE=apm-agent-php_${VERSION}_all.apk
    wget -q "${GITHUB_RELEASES_URL}/v${VERSION}/${PACKAGE}" -O "build/releases/${PACKAGE}"
    wget -q "${GITHUB_RELEASES_URL}/v${VERSION}/${PACKAGE}.sha512" -O "build/releases/${PACKAGE}.sha512"
    shasum -a 512 -c "build/releases/${PACKAGE}.sha512"
    apk add --allow-untrusted --verbose --no-cache "build/releases/${PACKAGE}"
else
    ## Install apk package and configure the agent accordingly
    apk add --allow-untrusted --verbose --no-cache build/packages/*.apk
fi

## Verify if the elastic php agent is enabled
if ! php -m | grep -q 'elastic' ; then
    echo 'Extension has not been installed.'
    exit 1
fi

## Validate the installation works as expected with composer
composer install
composer run-script run_component_tests
