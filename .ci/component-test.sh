#!/usr/bin/env bash
set -xe

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
mkdir -p /app/build/
composer run-script run_component_tests 2>&1 | tee /app/build/run_component_tests_output.txt
run_component_tests_exit_code=$?
if [[ $run_component_tests_exit_code -ne 0 ]] ; then
    echo 'Something bad happened when running the tests, see the output from the syslog'

    if [ -f "/var/log/syslog" ]; then
        cp "/var/log/syslog" /app/build/run_component_tests_syslog.txt
        cat "/var/log/syslog"
        echo '================= end of /var/log/syslog content ================='
    else
        if [ -f "/var/log/messages" ]; then
            cp "/var/log/messages" /app/build/run_component_tests_syslog.txt
            cat "/var/log/messages"
            echo '================= end of /var/log/messages content ================='
        else
            echo 'syslog log file not found'
        fi
    fi

    exit 1
fi
