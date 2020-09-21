#!/usr/bin/env sh
set -x

## Install apk package and configure the agent accordingly
apk add --allow-untrusted --verbose --no-cache build/packages/*.apk

## Verify if the elastic php agent is enabled
if ! php -m | grep -q 'elastic' ; then
    echo 'Extension has not been installed.'
    exit 1
fi

## Validate the installation works as expected with composer
composer install
composer run-script run_component_tests
