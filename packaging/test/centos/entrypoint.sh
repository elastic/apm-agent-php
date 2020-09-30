#!/usr/bin/env bash
set -xe

if [ "${TYPE}" == "rpm" ] ; then
    ## Install rpm package and configure the agent accordingly
    rpm -ivh build/packages/*.rpm
elif [ "${TYPE}" == "release-github" ] ; then
    ## fpm replaces - with _ in the version for rpms.
    PACKAGE=apm-agent-php-${VERSION/-/_}-1.noarch.rpm
    rpm --import https://artifacts.elastic.co/GPG-KEY-elasticsearch
    wget -q "${GITHUB_RELEASE_URL}/v${VERSION}/${PACKAGE}"
    wget -q "${GITHUB_RELEASE_URL}/v${VERSION}/${PACKAGE}.sha512"
    shasum -a 512 -c "${PACKAGE}.sha512"
    rpm -ivh "${PACKAGE}"
elif [ "${TYPE}" == "release-tar-github" ] ; then
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
