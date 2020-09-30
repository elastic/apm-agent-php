#!/usr/bin/env bash
set -xe

if [ "${TYPE}" == "rpm" ] ; then
    ## Install rpm package and configure the agent accordingly
    rpm -ivh build/packages/*.rpm
elif [ "${TYPE}" == "release-github" ] ; then
    mkdir -p build/releases
    ## fpm replaces - with _ in the version for rpms.
    PACKAGE=apm-agent-php-${VERSION/-/_}-1.noarch.rpm
    rpm --import https://artifacts.elastic.co/GPG-KEY-elasticsearch
    wget -q "${GITHUB_RELEASES_URL}/v${VERSION}/${PACKAGE}" -O "build/releases/${PACKAGE}"
    wget -q "${GITHUB_RELEASES_URL}/v${VERSION}/${PACKAGE}.sha512" -O "build/releases/${PACKAGE}"
    shasum -a 512 -c "build/releases/${PACKAGE}.sha512"
    rpm -ivh "build/releases/${PACKAGE}"
elif [ "${TYPE}" == "release-tar-github" ] ; then
    mkdir -p build/releases
    PACKAGE=apm-agent-php.tar
    wget -q "${GITHUB_RELEASES_URL}/v${VERSION}/${PACKAGE}" -O "build/releases/${PACKAGE}"
    wget -q "${GITHUB_RELEASES_URL}/v${VERSION}/${PACKAGE}.sha512" -O "build/releases/${PACKAGE}"
    shasum -a 512 -c "build/releases/${PACKAGE}.sha512"
    ## Install tar package and configure the agent accordingly
    tar -xf build/releases/${PACKAGE} -C /
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
