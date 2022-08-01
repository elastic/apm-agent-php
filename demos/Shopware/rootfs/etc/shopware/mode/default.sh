#!/usr/bin/env bash

source /etc/shopware/mode/tasks/wait_for_mysql.sh
source /etc/shopware/mode/tasks/install_update.sh

for f in /etc/shopware/scripts/on-startup/*; do source $f; done

/usr/bin/supervisord -c /etc/supervisord.conf