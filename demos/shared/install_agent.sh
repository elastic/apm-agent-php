#!/usr/bin/env bash

set -xe

PHP_AGENT_DEFAULT_RELEASE_VERSION_TO_INSTALL=1.7.0

install_local_code () {
    cat /app/local_agent_code_php.ini
    local php_conf_d_dir_full_path=/usr/local/etc/php/conf.d
    if [ "${PHP_CONF_D_DIR_FULL_PATH}" != "" ]; then
        php_conf_d_dir_full_path="${PHP_CONF_D_DIR_FULL_PATH}"
    fi

    ln -s /app/local_agent_code_php.ini "${php_conf_d_dir_full_path}/98-elastic-apm.ini"
}

install_local_package_from_url () {
    local package_url="$1"
    local package_file_name=$(basename -- "${package_url}")
    local package_extension="${package_url##*.}"
    local downloaded_package_full_path="/tmp/${package_file_name}"
    set -xe
    echo "curl -fsSL \"${package_url}\" \> \"${downloaded_package_full_path}\""
    curl -fsSL "${package_url}" > "${downloaded_package_full_path}"
    ls -l "${downloaded_package_full_path}"


    case "${package_extension}" in
        'apk')
                apk add --allow-untrusted "${downloaded_package_full_path}"
                ;;
        'deb')
                dpkg -i "${downloaded_package_full_path}"
                ;;
        'rpm')
                rpm -ivh "${downloaded_package_full_path}"
                ;;
        *)
                echo "Error: Unexpected agent package extension: \`${package_extension}' (package_url: ${package_url})"
                print_command_line_help
                exit 1
                ;;
    esac

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

    if [ "${PHP_AGENT_INSTALL_RELEASE_VERSION}" == "none" ]; then
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
