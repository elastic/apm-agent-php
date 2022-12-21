#!/usr/bin/env bash

set -xe

PHP_AGENT_DEFAULT_RELEASE_VERSION_TO_INSTALL=1.7.0

install_local_code () {
    cat /app/local_agent_code_php.ini
    ln -s /app/local_agent_code_php.ini /usr/local/etc/php/conf.d/98-elastic-apm.ini
}

install_local_package_from_url () {
    package_url="$1"
    curl -fsSL "${package_url}" > /tmp/apm-gent-php.deb
    dpkg -i /tmp/apm-gent-php.deb
    grep PHP_ELASTIC_APM_VERSION "/opt/elastic/apm-agent-php/src/ext/elastic_apm_version.h"
    grep VERSION "/opt/elastic/apm-agent-php/src/ElasticApm/ElasticApm.php"
}

detect_and_install () {
    set | grep ELASTIC || true
    set | grep PHP || true

    if [ -n "${PHP_AGENT_INSTALL_LOCAL_EXTENSION_BINARY}" ]; then
        echo "Installing agent using local code (PHP_AGENT_INSTALL_LOCAL_EXTENSION_BINARY: ${PHP_AGENT_INSTALL_LOCAL_EXTENSION_BINARY}) ..."
        install_local_code
        return
    fi

    if [ -n "${PHP_AGENT_INSTALL_PACKAGE_URL}" ]; then
        echo "Installing agent using package URL (PHP_AGENT_INSTALL_PACKAGE_URL: ${PHP_AGENT_INSTALL_PACKAGE_URL}) ..."
        install_local_package_from_url "${PHP_AGENT_INSTALL_PACKAGE_URL}"
        return
    fi

    agent_version="${PHP_AGENT_INSTALL_RELEASE_VERSION:-${PHP_AGENT_DEFAULT_RELEASE_VERSION_TO_INSTALL}}"
    echo "Installing agent using release version ${agent_version} (PHP_AGENT_INSTALL_RELEASE_VERSION: ${PHP_AGENT_INSTALL_RELEASE_VERSION})..."
    install_local_package_from_url "https://github.com/elastic/apm-agent-php/releases/download/v${agent_version}/apm-agent-php_${agent_version}_all.deb"
}

main () {
    detect_and_install
}

main
