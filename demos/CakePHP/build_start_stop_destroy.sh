#!/usr/bin/env bash

set -e

#
# App web: http://<external IP of test machine>:8080/
# Kibana: http://<external IP of test machine>:5601/
#

function main() {
    local this_script_name
    this_script_name="$( basename "${BASH_SOURCE[0]}" )"
    local this_script_dir
    this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
    this_script_dir="$( realpath "${this_script_dir}" )"
    local demos_shared_dir
    demos_shared_dir="$( realpath "${this_script_dir}/../shared" )"

    source "${this_script_dir}/demo_specific_options.sh"

    local impl_script_cmd_opts
    impl_script_cmd_opts=()
    if [ -n "${web_frontend_service}" ]; then
        impl_script_cmd_opts=("${impl_script_cmd_opts[@]}" --web-frontend-service="${web_frontend_service}")
    fi
    if [ -n "${separate_php_backend_service}" ]; then
        impl_script_cmd_opts=("${impl_script_cmd_opts[@]}" --separate-php-backend-service="${separate_php_backend_service}")
    fi

    "${demos_shared_dir}/${this_script_name}" "${impl_script_cmd_opts[@]}" "$@"
}

main "$@"
