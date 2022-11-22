#!/usr/bin/env bash

set -e

function verify_mandatory_env_var_is_set() {
    local env_var_name=$1
    if [ -z "${env_var_name}" ] ; then
        echo "Argument env_var_name is mandatory"
        exit 1
    fi

    if [ -z "${!env_var_name}" ] ; then
        echo "Mandatory environment variable ${env_var_name} is not set"
        exit 1
    fi

    echo "${env_var_name}: ${!env_var_name}"
}

function main() {
    verify_mandatory_env_var_is_set PHP_AGENT_INSTALL_LOCAL_EXTENSION_BINARY
    verify_mandatory_env_var_is_set PHP_AGENT_INSTALL_LOCAL_SRC

    local docker_compose_base_yml_file_local="docker-compose.yml"
    if [ -n "${DOCKER_COMPOSE_BASE_YML_FILE}" ]; then
        docker_compose_base_yml_file_local="${DOCKER_COMPOSE_BASE_YML_FILE}"
    fi

    export DOCKER_COMPOSE_OPTIONS="-f docker-compose_local_agent_code.yml -f ${docker_compose_base_yml_file_local}"

    local this_script_dir
    this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
    this_script_dir="$( realpath "${this_script_dir}" )"
    "${this_script_dir}/build_start_stop_destroy_loop.sh"
}

main
