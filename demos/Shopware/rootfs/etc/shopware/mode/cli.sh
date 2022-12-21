#!/usr/bin/env bash

for f in /etc/shopware/scripts/on-startup/*; do source $f; done

nginx -c /etc/nginx/cron.conf

sudo -E -u www-data php /var/www/html/bin/console ${@:2}