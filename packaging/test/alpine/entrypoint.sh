#!/usr/bin/env sh
set -x

if [ "${TYPE}" = "release" ] ; then
    PACKAGE=apm-agent-php_${VERSION}-preview_all.apk
    wget -q "https://github.com/elastic/apm-agent-php/releases/download/v${VERSION}/${PACKAGE}"
    wget -q "https://github.com/elastic/apm-agent-php/releases/download/v${VERSION}/${PACKAGE}.sha512"
    shasum -a 512 -c "${PACKAGE}.sha512"
    apk add --allow-untrusted --verbose --no-cache "${PACKAGE}"
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
