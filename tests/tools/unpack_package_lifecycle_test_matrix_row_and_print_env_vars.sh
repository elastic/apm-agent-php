#!/usr/bin/env bash
set -e

this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
this_script_dir="$( realpath "${this_script_dir}" )"
repo_root_dir="$( realpath "${this_script_dir}/../.." )"

source "${repo_root_dir}/.ci/unpack_package_lifecycle_test_matrix_row.sh" "$@" &> /dev/null
env | grep ELASTIC_ 2> /dev/null
