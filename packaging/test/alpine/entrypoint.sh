#!/usr/bin/env sh
set -x

###################
#### VARIABLES ####
###################
BUILD_RELEASES_FOLDER=build/releases
BUILD_PACKAGES=build/packages

###################
#### FUNCTIONS ####
###################
download() {
    package=$1
    folder=$2
    url=$3
    mkdir -p "${folder}"
    wget -q "${url}/${package}" -O "${folder}/${package}"
    wget -q "${url}/${package}.sha512" -O "${folder}/${package}.sha512"
    cd "${folder}" || exit
    shasum -a 512 -c "${package}.sha512"
    cd - || exit
}

validate_if_agent_is_uninstalled() {
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

validate_if_agent_is_enabled() {
    ## Validate if the elastic php agent is enabled
    if ! php -m | grep -q 'elastic' ; then
        echo 'Extension has not been installed.'
        exit 1
    fi
}

validate_agent_installation() {
    .ci/validate_agent_installation.sh || exit $?
}

##############
#### MAIN ####
##############
if [ "${TYPE}" = "release-github" ] ; then
    if apk --version 2>&1 | grep -q "apk-tools 3"; then
        echo "Detected apk-tools v3, downloading APK v3 format package"
        APKARCH=$(apk --print-arch)
        PKGVER=$(echo ${VERSION} | tr '-' '_')
        PACKAGE=apm-agent-php-${PKGVER}-r0.${APKARCH}.apk
    else
        echo "Detected apk-tools v2, downloading APK v2 format package"
        PACKAGE=apm-agent-php_${VERSION}_all.apk
    fi
    download "${PACKAGE}" "${BUILD_RELEASES_FOLDER}" "${GITHUB_RELEASES_URL}/v${VERSION}"
    apk add --allow-untrusted --verbose --no-cache "${BUILD_RELEASES_FOLDER}/${PACKAGE}"
else
    ls -l $BUILD_PACKAGES
    ## Install apk package - select v2 or v3 format based on apk-tools version
    if apk --version 2>&1 | grep -q "apk-tools 3"; then
        echo "Detected apk-tools v3, installing APK v3 format package"
        apk add --allow-untrusted --verbose --no-cache $BUILD_PACKAGES/apm-agent-php-*-r0.*.apk
    else
        echo "Detected apk-tools v2, installing APK v2 format package"
        apk add --allow-untrusted --verbose --no-cache $BUILD_PACKAGES/apm-agent-php_*.apk
    fi
fi

validate_if_agent_is_enabled

validate_agent_installation

## Validate the uninstallation works as expected
set -ex
if [ "${TYPE}" = "apk-uninstall" ] ; then
    apk del --verbose --no-cache "${PACKAGE}"
    validate_if_agent_is_uninstalled
fi
