#!/usr/bin/env bash

set -e

function main() {
    export BUILD_START_STOP_DESTROY_LOOP_DOCKER_SERVICE_TO_START=web

    local this_script_dir
    this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
    this_script_dir="$( realpath "${this_script_dir}" )"
    "${this_script_dir}/build_start_stop_destroy_loop_local_agent_code.sh"
}

main
