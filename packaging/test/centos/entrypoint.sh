#!/usr/bin/env bash
set -xe

###################
#### VARIABLES ####
###################
BUILD_RELEASES_FOLDER=build/releases

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

function verify_uninstalled() {
    ## Verify if the elastic php agent has been uninstalled
    php -m > /dev/null 2>&1
    if php -m | grep -q "Unable to load dynamic library '/opt/elastic/apm-agent-php/extensions"  ; then
        echo 'Extension has not been uninstalled.'
        exit 1
    fi
    if php -m | grep -q 'elastic' ; then
        echo 'Extension has not been uninstalled.'
        exit 1
    fi
}

##############
#### MAIN ####
##############
if [[ "${TYPE}" == "rpm" || "${TYPE}" == "rpm-uninstall" ]] ; then
    ## Install rpm package and configure the agent accordingly
    rpm -ivh build/packages/*.rpm
elif [ "${TYPE}" == "release-github" ] ; then
    ## fpm replaces - with _ in the version for rpms.
    PACKAGE=apm-agent-php-${VERSION/-/_}-1.noarch.rpm
    download "${PACKAGE}" "${BUILD_RELEASES_FOLDER}" "${GITHUB_RELEASES_URL}/v${VERSION}"
    rpm -ivh "${BUILD_RELEASES_FOLDER}/${PACKAGE}"
elif [ "${TYPE}" == "release-tar-github" ] ; then
    PACKAGE=apm-agent-php.tar
    download "${PACKAGE}" "${BUILD_RELEASES_FOLDER}" "${GITHUB_RELEASES_URL}/v${VERSION}"
    ## Install tar package and configure the agent accordingly
    tar -xf ${BUILD_RELEASES_FOLDER}/${PACKAGE} -C /
    # shellcheck disable=SC1091
    source /opt/elastic/apm-agent-php/bin/post-install.sh
else
    ## Install tar package and configure the agent accordingly
    tar -xf build/packages/*.tar -C /
    # shellcheck disable=SC1091
    source /opt/elastic/apm-agent-php/bin/post-install.sh
fi

## Verify if the elastic php agent is enabled
if ! php -m | grep -q 'elastic' ; then
    echo 'Extension has not been installed.'
    exit 1
fi

## Validate the installation works as expected with composer
composer install
/usr/sbin/rsyslogd
if ! composer run-script run_component_tests ; then
    echo 'Something bad happened when running the tests, see the output from the syslog'
    cat /var/log/syslog
    exit 1
fi

## Validate the uninstallation works as expected
set -ex
if [ "${TYPE}" == "rpm-uninstall" ] ; then
    rpm -e "${PACKAGE}"
    verify_uninstalled
elif [ "${TYPE}" == "tar-uninstall" ] ; then
    # shellcheck disable=SC1091
    source /opt/elastic/apm-agent-php/bin/before-uninstall.sh
    verify_uninstalled
fi
