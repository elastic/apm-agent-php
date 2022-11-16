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
    command_to_run=$1

    clear
    echo "========================================"
    echo "============"
    echo "==="
    echo "${command_to_run}"
    echo "==="
    echo "============"
    echo "========================================"
    ${command_to_run}
}

function wait_for_approval_and_run_command() {
    command_to_run=$1

    echo_vertical_space
	echo "Press [CTRL+C] to stop"
	echo "Press any other key to run '${command_to_run}'"
    echo_vertical_space
    read

    run_command "${command_to_run}"
}

function cleanup () {
    wait_for_approval_and_run_command "${docker_cmd_prefix} down -v --remove-orphans"
}

function main() {
    echo "DOCKER_COMPOSE_OPTIONS: ${DOCKER_COMPOSE_OPTIONS}"

    set | grep ELASTIC

    docker_cmd_prefix="docker-compose"
    if [ -n "${DOCKER_COMPOSE_OPTIONS}" ]; then
        docker_cmd_prefix="${docker_cmd_prefix} ${DOCKER_COMPOSE_OPTIONS}"
    fi

    if [ -n "${Z_LOCAL_ACTION}" ]; then
        run_command "${docker_cmd_prefix} ${Z_LOCAL_ACTION}"
    else
        trap cleanup EXIT
        while :
        do
            wait_for_approval_and_run_command "${docker_cmd_prefix} build"
            wait_for_approval_and_run_command "${docker_cmd_prefix} up"
            wait_for_approval_and_run_command "${docker_cmd_prefix} stop"
            wait_for_approval_and_run_command "${docker_cmd_prefix} down -v --remove-orphans"
        done
    fi
}

main
