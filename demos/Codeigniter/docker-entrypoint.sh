#!/usr/bin/env bash

php spark migrate -all
php spark db:seed BlogSeeder

chown -R www-data:www-data /var/www

php-fpm