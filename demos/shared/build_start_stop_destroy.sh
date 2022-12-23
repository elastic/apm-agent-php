#!/usr/bin/env bash

set -e

function print_command_line_help () {
    local defaults="Default values:"
    local new_line_indent="\n\t"
    local script_name
    script_name="$( basename "${BASH_SOURCE[0]}" )"
    local cli_format="Command line format:${new_line_indent}${script_name}"

    cli_format="${cli_format} [--agent-local-extension-binary=<full path to elastic_apm.so>]"
    cli_format="${cli_format} [--agent-local-src=<full path to repo_root/src directory>]"

    cli_format="${cli_format} [--agent-package-url=<URL to .deb file>]"

    cli_format="${cli_format} [--agent-release-version=<release version for example: 1.6.2>]"

    cli_format="${cli_format} [--app-only]"

    cli_format="${cli_format} [--pause-between-steps]"

    cli_format="${cli_format} [--separate-php-backend-service=<service name>]"

    cli_format="${cli_format} [--use-valgrind]"

    cli_format="${cli_format} [--web-frontend-service=<service name>]"

    cli_format="${cli_format} [--help]"

    echo -e "${cli_format}"
    echo -e "${defaults}"
}

function echo_env_var_if_set() {
    local env_var_name=$1
    if [ -n "${!env_var_name}" ] ; then
        echo "${env_var_name}: ${!env_var_name}"
    fi
}

function pause_between_steps_if_set() {
    if [ "${pause_between_steps}" == "true" ]; then
        read -p "Press [Enter] key to continue..."
    fi
}

function parse_command_line_arguments () {
    local all_args="$*"
    for arg in "$@"; do
        shift
        case "${arg}" in
            --agent-local-extension-binary=*)
                    agent_local_extension_binary="${arg#*=}"
                    ;;
            --agent-local-src=*)
                    agent_local_src="${arg#*=}"
                    ;;
            --agent-package-url=*)
                    agent_package_url="${arg#*=}"
                    ;;
            --agent-release-version=*)
                    agent_release_version="${arg#*=}"
                    ;;
            --app-only)
                    app_only="true"
                    ;;
            --pause-between-steps)
                    pause_between_steps="true"
                    ;;
            --separate-php-backend-service=*)
                    separate_php_backend_service="${arg#*=}"
                    ;;
            --use-valgrind)
                    use_valgrind="true"
                    ;;
            --web-frontend-service=*)
                    web_frontend_service="${arg#*=}"
                    ;;
            '--help')
                    print_command_line_help
                    exit 0
                    ;;
            *)
                    if [ "${is_inside_command_to_run}" = "no" ]; then
                        echo "Error: Unknown argument: \`${arg}' (all the arguments: \`${all_args}')"
                        print_command_line_help
                        exit 1
                    fi
                    command_to_run+=("${arg}")
                    ;;
        esac
    done

    echo_env_var_if_set agent_local_extension_binary
    echo_env_var_if_set agent_local_src
    echo_env_var_if_set agent_package_url
    echo_env_var_if_set agent_release_version
    echo_env_var_if_set app_only
    echo_env_var_if_set pause_between_steps
    echo_env_var_if_set separate_php_backend_service
    echo_env_var_if_set use_valgrind
    echo_env_var_if_set web_frontend_service

    if [ -n "${agent_local_extension_binary}" ] || [ -n "${agent_local_src}" ]; then
        if [ -z "${agent_local_extension_binary}" ]; then
            echo "Command line option --agent-local-src is provided while --agent-local-extension-binary is not"
            exit 1
        fi
        if [ -z "${agent_local_src}" ]; then
            echo "Command line option --agent-local-extension-binary is provided while --agent-local-src is not"
            exit 1
        fi
    fi
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

    pause_between_steps_if_set
}

function cleanup () {
    pause_between_steps_if_set
    ${docker_cmd_prefix} down -v --remove-orphans
}

function main() {
    set | grep ELASTIC

    parse_command_line_arguments "$@"
    pause_between_steps_if_set

    if [ -n "${agent_local_extension_binary}" ]; then
        export PHP_AGENT_INSTALL_LOCAL_EXTENSION_BINARY="${agent_local_extension_binary}"
        export PHP_AGENT_INSTALL_LOCAL_SRC="${agent_local_src}"

        docker_compose_options="-f docker-compose_local_agent_code.yml -f docker-compose.yml"
    fi

    if [ -n "${agent_package_url}" ]; then
        export PHP_AGENT_INSTALL_PACKAGE_URL="${agent_package_url}"
    fi

    if [ -n "${agent_release_version}" ]; then
        export PHP_AGENT_INSTALL_RELEASE_VERSION="${agent_release_version}"
    fi

    if [ "${use_valgrind}" == "true" ]; then
        export USE_VALGRIND="true"
    fi

    docker_cmd_prefix="docker-compose"
    if [ -n "${docker_compose_options}" ]; then
        docker_cmd_prefix="${docker_cmd_prefix} ${docker_compose_options}"
    fi

    trap cleanup EXIT

    run_command ${docker_cmd_prefix} build

    if [ -z "${web_frontend_service}" ]; then
        web_frontend_service="app"
    fi

    local docker_up_cmd="${docker_cmd_prefix} up -d"
    if [ "${app_only}" == "true" ]; then
        export ELASTIC_APM_DISABLE_SEND
        ELASTIC_APM_DISABLE_SEND=true
        docker_up_cmd="${docker_up_cmd} ${web_frontend_service}"
    fi

    run_command ${docker_up_cmd}

    local follow_logs_for_servies
    follow_logs_for_servies=("${web_frontend_service}")
    if [ -n "${separate_php_backend_service}" ]; then
        follow_logs_for_servies=("${follow_logs_for_servies[@]}" "${separate_php_backend_service}")
    fi

    if [ "${app_only}" != "true" ]; then
        follow_logs_for_servies=("${follow_logs_for_servies[@]}" apm-server)
    fi
    run_command ${docker_cmd_prefix} logs --follow "${follow_logs_for_servies[@]}"
}

main "$@"
