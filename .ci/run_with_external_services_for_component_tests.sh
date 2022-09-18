#!/usr/bin/env bash
set -xe

this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
this_script_dir="$( realpath "${this_script_dir}" )"

. "${this_script_dir}/start_with_external_services_for_component_tests.sh"

"$@"

"${this_script_dir}/stop_with_external_services_for_component_tests.sh"
