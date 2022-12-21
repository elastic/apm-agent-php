#!/usr/bin/env bash

for f in /etc/shopware/scripts/on-startup/*; do source $f; done

/usr/bin/supervisord -c /etc/supervisord.conf