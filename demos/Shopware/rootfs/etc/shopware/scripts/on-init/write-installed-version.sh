#!/usr/bin/env bash

if [[ -n "$INSTALLED_SHOPWARE_VERSION" ]]; then
    echo 'Written'
    echo "$INSTALLED_SHOPWARE_VERSION" > /state/installed_version
fi