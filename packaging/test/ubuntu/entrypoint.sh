#!/usr/bin/env bash
set -xe

if [ "${TYPE}" == "deb" ] ; then
    ## Install debian package and configure the agent accordingly
    dpkg -i build/packages/*.deb
elif [ "${TYPE}" == "release" ] ; then
    PACKAGE=apm-agent-php_${VERSION}-preview_all.deb
    wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | apt-key add -
    wget -q "https://github.com/elastic/apm-agent-php/releases/download/v${VERSION}/${PACKAGE}"
    wget -q "https://github.com/elastic/apm-agent-php/releases/download/v${VERSION}/${PACKAGE}.sha512"
    shasum -a 512 -c "${PACKAGE}.sha512"
    dpkg -i "${PACKAGE}"
elif [ "${TYPE}" == "release-tar" ] ; then
    PACKAGE=apm-agent-php.tar
    wget -q "https://github.com/elastic/apm-agent-php/releases/download/v${VERSION}/${PACKAGE}"
    wget -q "https://github.com/elastic/apm-agent-php/releases/download/v${VERSION}/${PACKAGE}.sha512"
    shasum -a 512 -c "${PACKAGE}.sha512"
    ## Install tar package and configure the agent accordingly
    tar -xf ${PACKAGE} -C /
    # shellcheck disable=SC1091
    source /.scripts/after_install
else
    ## Install tar package and configure the agent accordingly
    tar -xf build/packages/*.tar -C /
    # shellcheck disable=SC1091
    source /.scripts/after_install
fi

## Verify if the elastic php agent is enabled
if ! php -m | grep -q 'elastic' ; then
    echo 'Extension has not been installed.'
    exit 1
fi

## Validate the installation works as expected with composer
composer install
composer run-script run_component_tests
