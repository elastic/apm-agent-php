#!/usr/bin/env bash
set -x

## Install debian package and configure the agent accordingly

## TODO, php.ini setup to be done by the deb package.
echo 'extension=elastic_apm.so' > /usr/local/etc/php/php.ini
echo 'elastic_apm.bootstrap_php_part_file=/usr/share/php/apm-agent-php/bootstrap_php_part.php' >> /usr/local/etc/php/php.ini
dpkg -i build/packages/*.deb
cp /usr/share/php/extensions/elastic_apm.so /usr/local/lib/php/extensions/no-debug-non-zts-20170718/
php -m

## Validate the installation works as expected with composer
composer install
composer run-script run_component_tests_standalone_envs
