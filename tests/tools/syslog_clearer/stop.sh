#!/usr/bin/env bash
set -e

this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
this_script_dir="$( realpath "${this_script_dir}" )"
source "${this_script_dir}/../shared.sh"

# shellcheck disable=SC2154 # repo_root_dir repo_root_dir is assigned in shared.sh
php "${repo_root_dir}/tests/ElasticApmTests/ComponentTests/Util/stopSyslogClearer.php" "$@"
