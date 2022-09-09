#!/usr/bin/env bash
set -xe

export ELASTIC_APM_ENABLED=false

echo 'Installed PHP extensions:'
php -m
echo 'PHP info:'
php -i
echo 'Set environment variables:'
set | grep -i elastic

this_script_full_path="${BASH_SOURCE[0]}"
cat "${this_script_full_path}"
