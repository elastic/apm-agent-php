#!/usr/bin/env bash
set -xe

if [ "${TYPE}" == "deb" ] ; then
    ## Install debian package and configure the agent accordingly
    dpkg -i build/packages/*.deb
elif [ "${TYPE}" == "release" ] ; then
    PACKAGE=apm-agent-php_${VERSION}_all.deb
    wget -q https://artifacts.elastic.co/GPG-KEY-elasticsearch
    apt-key add GPG-KEY-elasticsearch
    gpg --import GPG-KEY-elasticsearch
    wget -q "${GITHUB_RELEASE_URL}/v${VERSION}/${PACKAGE}"
    wget -q "${GITHUB_RELEASE_URL}/v${VERSION}/${PACKAGE}.sha512"
    shasum -a 512 -c "${PACKAGE}.sha512"
    dpkg-sig --verify "${PACKAGE}"
    dpkg -i "${PACKAGE}"
elif [ "${TYPE}" == "release-tar" ] ; then
    PACKAGE=apm-agent-php.tar
    wget -q "${GITHUB_RELEASE_URL}/v${VERSION}/${PACKAGE}"
    wget -q "${GITHUB_RELEASE_URL}/v${VERSION}/${PACKAGE}.sha512"
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
