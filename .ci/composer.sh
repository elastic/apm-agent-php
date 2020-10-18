#!/usr/bin/env bash
set -xe

PHP_INI=/usr/local/etc/php/php.ini
make install
echo 'extension=elastic_apm.so' > ${PHP_INI}
echo 'elastic_apm.bootstrap_php_part_file=/app/src/bootstrap_php_part.php' >> ${PHP_INI}
php -m
cd /app

##
## Validate the installation works as expected with composer
##

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

if ! composer run-script run_component_tests ; then
    echo 'Something bad happened when running the tests, see the output from the syslog'

    if [ -f "/var/log/syslog" ]; then
        cat "/var/log/syslog"
    else
        if [ -f "/var/log/messages" ]; then
        cat "/var/log/messages"
        else
            echo 'syslog's log file not found'
            exit 1
        fi
    fi

    exit 1
fi
