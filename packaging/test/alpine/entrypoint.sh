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
set +x
/usr/sbin/rsyslogd || true
if ! composer run-script run_component_tests ; then
    echo 'Something bad happened when running the tests, see the output from the syslog'
    cat /var/log/syslog
    exit 1
fi
