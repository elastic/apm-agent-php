#!/usr/bin/env bash
set -xe

## This make runs PHPT
make test

cd /app

# Install 3rd party dependencies
composer install

# Run static_check_and_run_unit_tests
composer run-script static_check_and_run_unit_tests
