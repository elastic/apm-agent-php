#!/usr/bin/env bash

set -e

#
# App web front: http://<external IP of test machine>:8080/
# Kibana: http://<external IP of test machine>:5601/
#

function echo_vertical_space() {
    # shellcheck disable=SC2034
    for i in {1..10}
    do
        echo ""
    done
}

function run_command() {
    local command_to_run="$@"

    clear
    echo "========================================"
    echo "============"
    echo "==="
    echo "${command_to_run[*]}"
    echo "==="
    echo "============"
    echo "========================================"
    ${command_to_run}
}

function wait_for_approval_and_run_command() {
    local command_to_run="$@"

    echo_vertical_space
    echo "Press [CTRL+C] to stop"
    echo "Press any other key to run ${command_to_run[*]}"
    echo_vertical_space
    read

    run_command "${command_to_run[@]}"
}

function main() {
    echo "BUILD_START_STOP_DESTROY_LOOP_DOCKER_COMPOSE_OPTIONS: ${BUILD_START_STOP_DESTROY_LOOP_DOCKER_COMPOSE_OPTIONS}"

    set | grep ELASTIC

    local docker_cmd_prefix="docker-compose"
    if [ -n "${BUILD_START_STOP_DESTROY_LOOP_DOCKER_COMPOSE_OPTIONS}" ]; then
        docker_cmd_prefix="${docker_cmd_prefix} ${BUILD_START_STOP_DESTROY_LOOP_DOCKER_COMPOSE_OPTIONS}"
    fi

    local docker_up_cmd="${docker_cmd_prefix} up"
    if [ -n "${BUILD_START_STOP_DESTROY_LOOP_DOCKER_SERVICE_TO_START}" ]; then
        docker_up_cmd="${docker_up_cmd} ${BUILD_START_STOP_DESTROY_LOOP_DOCKER_SERVICE_TO_START}"
    fi

    while :
    do
        wait_for_approval_and_run_command ${docker_cmd_prefix} build
        wait_for_approval_and_run_command ${docker_up_cmd}
        wait_for_approval_and_run_command ${docker_cmd_prefix} stop
        wait_for_approval_and_run_command ${docker_cmd_prefix} down -v --remove-orphans
    done
}

main
