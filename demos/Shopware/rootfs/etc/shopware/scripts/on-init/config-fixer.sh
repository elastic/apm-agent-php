#!/usr/bin/env bash

rm /var/www/html/config/services/defaults_test.xml
cp /etc/shopware/configs/services/jwt.xml /var/www/html/config/services/
cp /etc/shopware/configs/services/services.xml /var/www/html/config/