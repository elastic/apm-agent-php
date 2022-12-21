#!/usr/bin/env bash

if [[ $DISABLE_ADMIN_WORKER == "true" ]]; then
    cp /etc/shopware/configs/disable_admin_worker.yml /var/www/html/config/packages/disable_admin_worker.yml
fi