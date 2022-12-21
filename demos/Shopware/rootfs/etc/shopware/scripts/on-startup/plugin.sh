#!/usr/bin/env bash

if [[ -n $ACTIVE_PLUGINS ]]; then
  sudo -E -u www-data php /var/www/html/bin/console plugin:refresh

  sudo -E -u www-data php /var/www/html/bin/console plugin:install --activate $ACTIVE_PLUGINS -n
  sudo -E -u www-data php /var/www/html/bin/console plugin:update $ACTIVE_PLUGINS -n
  sudo -E -u www-data php /var/www/html/bin/console cache:clear
fi
