#!/usr/bin/env bash
set -xe

###################
#### VARIABLES ####
###################
BUILD_RELEASES_FOLDER=build/releases

###################
#### FUNCTIONS ####
###################
function downloadWithShasumValidation() {
    package=$1
    folder=$2
    url=$3
    mkdir -p "${folder}"
    wget -q https://artifacts.elastic.co/GPG-KEY-elasticsearch -O "${folder}/GPG-KEY-elasticsearch"
    wget -q "${url}/${package}" -O "${folder}/${package}"
    wget -q "${url}/${package}.sha512" -O "${folder}/${package}.sha512"
    cd ${folder} || exit
    shasum -a 512 -c "${package}.sha512"
    cd -
}

##############
#### MAIN ####
##############
if [ "${TYPE}" == "deb" ] ; then
    ## Install debian package and configure the agent accordingly
    dpkg -i build/packages/*.deb
elif [ "${TYPE}" == "release-github" ] ; then
    PACKAGE=apm-agent-php_${VERSION}_all.deb
    downloadWithShasumValidation "${PACKAGE}" "${BUILD_RELEASES_FOLDER}" "${GITHUB_RELEASES_URL}/v${VERSION}"
    apt-key add "${BUILD_RELEASES_FOLDER}/GPG-KEY-elasticsearch"
    gpg --import "${BUILD_RELEASES_FOLDER}/GPG-KEY-elasticsearch"
    dpkg-sig --verify "${BUILD_RELEASES_FOLDER}/${PACKAGE}"
    dpkg -i "${BUILD_RELEASES_FOLDER}/${PACKAGE}"
elif [ "${TYPE}" == "release-tar-github" ] ; then
    PACKAGE=apm-agent-php.tar
    downloadWithShasumValidation "${PACKAGE}" "${BUILD_RELEASES_FOLDER}" "${GITHUB_RELEASES_URL}/v${VERSION}"
    ## Install tar package and configure the agent accordingly
    tar -xf ${BUILD_RELEASES_FOLDER}/${PACKAGE} -C /
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
