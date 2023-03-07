#!/usr/bin/env bash
set -xe -o pipefail

# Disable Elastic APM for any process outside the component tests to prevent noise in the logs
export ELASTIC_APM_ENABLED=false

PHP_INI=/usr/local/etc/php/php.ini
make install
echo 'extension=elastic_apm.so' > ${PHP_INI}
echo 'elastic_apm.bootstrap_php_part_file=/app/src/bootstrap_php_part.php' >> ${PHP_INI}
php -m
cd /app

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
composer run-script run_component_tests 2>&1 | tee /app/build/run_component_tests_output.txt

