#!/usr/bin/env sh
set -x

###################
#### VARIABLES ####
###################
BUILD_RELEASES_FOLDER=build/releases

##############
#### MAIN ####
##############
if [ "${TYPE}" = "release-github" ] ; then
    mkdir -p "${BUILD_RELEASES_FOLDER}"
    PACKAGE=apm-agent-php_${VERSION}_all.apk
    wget -q "${GITHUB_RELEASES_URL}/v${VERSION}/${PACKAGE}" -O "${BUILD_RELEASES_FOLDER}/${PACKAGE}"
    wget -q "${GITHUB_RELEASES_URL}/v${VERSION}/${PACKAGE}.sha512" -O "${BUILD_RELEASES_FOLDER}/${PACKAGE}.sha512"
    cd ${BUILD_RELEASES_FOLDER} || exit
    shasum -a 512 -c "${PACKAGE}.sha512"
    cd - || exit
    apk add --allow-untrusted --verbose --no-cache "${BUILD_RELEASES_FOLDER}/${PACKAGE}"
else
    ## Install apk package and configure the agent accordingly
    apk add --allow-untrusted --verbose --no-cache build/packages/*.apk
fi

## Verify if the elastic php agent is enabled
if ! php -m | grep -q 'elastic' ; then
    echo 'Extension has not been installed.'
    exit 1
fi

## Validate the installation works as expected with composer
composer install
syslogd
if ! composer run-script static_check_and_run_tests ; then
    echo 'Something bad happened when running the tests, see the output from the syslog'
    cat /var/log/messages
    exit 1
fi

## Validate the uninstallation works as expected
set -ex
if [ "${TYPE}" = "apk-uninstall" ] ; then
    apk del --verbose --no-cache "${PACKAGE}"
    ## Verify if the elastic php agent has been uninstalled
    php -m > /dev/null 2>&1
    if php -m | grep -q 'elastic' ; then
        echo 'Extension has not been uninstalled.'
        exit 1
    fi
fi
