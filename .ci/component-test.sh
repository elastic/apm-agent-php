#!/usr/bin/env bash
set -xe -o pipefail

# Disable Elastic APM for any process outside the component tests to prevent noise in the logs
export ELASTIC_APM_ENABLED=false

APP_FOLDER=/app
PHP_API=$(php -i | grep -i 'PHP API' | sed -e 's#.* =>##g' | awk '{print $1}')
PHP_EXECUTABLE=$(which php)

echo "BUILD ARCHITECTURE: $BUILD_ARCHITECTURE"

if [ -z "${BUILD_ARCHITECTURE}" ]
then
      echo "\$BUILD_ARCHITECTURE is not specified, assuming linux-x86-64"
      BUILD_ARCHITECTURE=linux-x86-64
fi
echo "BUILD ARCHITECTURE: $BUILD_ARCHITECTURE"



AGENT_EXTENSION_DIR=$APP_FOLDER/agent/native/_build/$BUILD_ARCHITECTURE-release/ext
AGENT_LOADER_DIR=$APP_FOLDER/agent/native/_build/$BUILD_ARCHITECTURE-release/loader/code

# copy loader library into extensions
cp ${AGENT_LOADER_DIR}/elastic_apm_loader.so ${AGENT_EXTENSION_DIR}/

AGENT_EXTENSION=$AGENT_EXTENSION_DIR/elastic_apm_loader.so

mkdir -p $APP_FOLDER/build


PHP_INI=/usr/local/etc/php/php.ini
echo "extension=${AGENT_EXTENSION}" > ${PHP_INI}
echo "elastic_apm.bootstrap_php_part_file=${APP_FOLDER}/agent/php/bootstrap_php_part.php" >> ${PHP_INI}
php -m
cd "${APP_FOLDER}"

# Install 3rd party dependencies
composer install

# Start syslog
if which syslogd; then
    syslogd
else
    if which rsyslogd; then
        rsyslogd
    else
        echo 'syslog is not installed'
        exit 1
    fi
fi

# Run component tests
mkdir -p ./build/
composer run-script run_component_tests 2>&1 | tee ${APP_FOLDER}/build/run_component_tests_output.txt

