#!/usr/bin/env bash
set -xe

PHP_INI=/usr/local/etc/php/php.ini
make install
echo 'extension=elastic_apm.so' > ${PHP_INI}
echo 'elastic_apm.bootstrap_php_part_file=/app/src/bootstrap_php_part.php' >> ${PHP_INI}
php -m
cd /app
composer install

set +x
/usr/sbin/rsyslogd || true
if ! composer run_component_tests_standalone_envs ; then
    echo 'Something bad happened when running the tests, see the output from the syslog'
    cat /var/log/syslog
    exit 1
fi
