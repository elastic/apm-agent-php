#!/usr/bin/env bash
set -x

## Install tar package and configure the agent accordingly
tar -xf build/packages/*.tar -C /
# shellcheck disable=SC1091
source .scripts/after_install

## Verify if the elastic php agent is enabled
if ! php -m | grep -q 'elastic' ; then
    echo 'Extension has not been installed.'
    exit 1
fi

## Validate the installation works as expected with composer
composer install
composer run-script run_component_tests_standalone_envs
