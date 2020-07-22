#!/usr/bin/env bash
set -xe

## Install rpm package and configure the agent accordingly

## TODO, php.ini setup to be done by the rpm package.
echo '[elastic]' >> /etc/opt/rh/rh-php72/php.ini
echo 'extension=elastic_apm.so' >> /etc/opt/rh/rh-php72/php.ini
echo 'elastic_apm.bootstrap_php_part_file=/usr/share/php/apm-agent-php/bootstrap_php_part.php' >> /etc/opt/rh/rh-php72/php.ini
rpm -ivh build/packages/*.rpm
cp /usr/share/php/extensions/elastic_apm.so /opt/rh/rh-php72/root/usr/lib64/php/modules/
php -m | grep -q 'elastic'


## Validate the installation works as expected with composer
composer install
composer run-script run_component_tests_standalone_envs
