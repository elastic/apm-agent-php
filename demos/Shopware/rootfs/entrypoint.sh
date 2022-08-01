#!/usr/bin/env bash

for f in /etc/shopware/scripts/on-init/*; do source $f; done

case $1 in
  cli)
    source /etc/shopware/mode/cli.sh
    ;;
  web)
    source /etc/shopware/mode/web.sh
    ;;
  maintenance)
    source /etc/shopware/mode/maintenance.sh
    ;;
  *)
    source /etc/shopware/mode/default.sh
    ;;
esac

