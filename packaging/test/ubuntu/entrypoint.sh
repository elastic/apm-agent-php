#!/usr/bin/env bash
set -xe

###################
#### VARIABLES ####
###################
BUILD_RELEASES_FOLDER=build/releases
BUILD_PACKAGES=build/packages

###################
#### FUNCTIONS ####
###################
function download() {
    package=$1
    folder=$2
    url=$3
    mkdir -p "${folder}"
    wget -q https://artifacts.elastic.co/GPG-KEY-elasticsearch -O "${folder}/GPG-KEY-elasticsearch"
    wget -q "${url}/${package}" -O "${folder}/${package}"
    wget -q "${url}/${package}.sha512" -O "${folder}/${package}.sha512"
    wget -q "${url}/${package}.asc" -O "${folder}/${package}.asc"
    cd "${folder}" || exit
    gpg --import "GPG-KEY-elasticsearch"
    shasum -a 512 -c "${package}.sha512"
    gpg --verify "${package}.asc" "${package}"
    cd -
}

function validate_if_agent_is_uninstalled() {
    ## Validate if the elastic php agent has been uninstalled
    php -m
    if php -m | grep -q "Unable to load dynamic library '/opt/elastic/apm-agent-php/extensions"  ; then
        echo 'Extension has not been uninstalled.'
        exit 1
    fi
    if php -m | grep -q 'elastic' ; then
        echo 'Extension has not been uninstalled.'
        exit 1
    fi
}

function validate_if_agent_is_enabled() {
    ## Validate if the elastic php agent is enabled
    if ! php -m | grep -q 'elastic' ; then
        echo 'Extension has not been installed.'
        exit 1
    fi
}

function validate_agent_installation() {
    .ci/validate_agent_installation.sh || exit $?
}

##############
#### MAIN ####
##############
if [[ "${TYPE}" == "deb" || "${TYPE}" == "deb-uninstall" ]] ; then
    ls -l $BUILD_PACKAGES
    ## Install debian package and configure the agent accordingly
    dpkg -i $BUILD_PACKAGES/*.deb
elif [ "${TYPE}" == "release-github" ] ; then
    PACKAGE=apm-agent-php_${VERSION}_all.deb
    download "${PACKAGE}" "${BUILD_RELEASES_FOLDER}" "${GITHUB_RELEASES_URL}/v${VERSION}"
    dpkg-sig --verify "${BUILD_RELEASES_FOLDER}/${PACKAGE}"
    dpkg -i "${BUILD_RELEASES_FOLDER}/${PACKAGE}"
elif [ "${TYPE}" == "release-tar-github" ] ; then
    PACKAGE=apm-agent-php.tar
    download "${PACKAGE}" "${BUILD_RELEASES_FOLDER}" "${GITHUB_RELEASES_URL}/v${VERSION}"
    ## Install tar package and configure the agent accordingly
    tar -xf ${BUILD_RELEASES_FOLDER}/${PACKAGE} -C /
    # shellcheck disable=SC1091
    source /opt/elastic/apm-agent-php/bin/post-install.sh
elif [ "${TYPE}" == "agent-upgrade" ] ; then
    PACKAGE=apm-agent-php_${VERSION}_all.deb
    download "${PACKAGE}" "${BUILD_RELEASES_FOLDER}" "${GITHUB_RELEASES_URL}/v${VERSION}"
    dpkg -i "${BUILD_RELEASES_FOLDER}/${PACKAGE}"
elif [ "${TYPE}" == "agent-upgrade-local" ] ; then
    dpkg -i build/local/*.deb
else
    ## Install tar package and configure the agent accordingly
    tar -xf $BUILD_PACKAGES/*.tar -C /
    ls -ltrah /opt/elastic/apm-agent-php/bin
    # shellcheck disable=SC1091
    source /opt/elastic/apm-agent-php/bin/post-install.sh
fi

validate_if_agent_is_enabled

if case $TYPE in agent-upgrade*) ;; *) false;; esac; then
    echo 'Validate installation runs after the agent upgrade.'
else
    validate_agent_installation
fi

## Validate the uninstallation works as expected
set -ex
if [ "${TYPE}" == "deb-uninstall" ] ; then
    dpkg --purge "${PACKAGE}"
    validate_if_agent_is_uninstalled
    if [ -d /opt/elastic/apm-agent-php/etc ] ; then
        echo 'Extension has not been uninstalled correctly.'
        exit 1
    fi
elif [ "${TYPE}" == "tar-uninstall" ] ; then
    # shellcheck disable=SC1091
    source /opt/elastic/apm-agent-php/bin/before-uninstall.sh
    validate_if_agent_is_uninstalled
elif case $TYPE in agent-upgrade*) ;; *) false;; esac; then
    ## Upgrade the agent version with the deb package and configure the agent accordingly
    dpkg -i $BUILD_PACKAGES/*.deb

    ## Validate agent is enabled
    validate_if_agent_is_enabled

    ## Run some tests
    validate_agent_installation
fi
