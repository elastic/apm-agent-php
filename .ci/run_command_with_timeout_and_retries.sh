#!/usr/bin/env bash
set -e

#
# run-test-command-with-timeout.sh --timeout=<number of seconds> --max-tries=<number> --wait-time-before-retry=<number of seconds> --retry-on-error=<yes|no> -- <command_to_run>
#

timeout_default=0
timeout=${timeout_default}
increase_timeout_exponentially_default=no
increase_timeout_exponentially=${increase_timeout_exponentially_default}
max_tries_default=1
max_tries=${max_tries_default}
wait_time_before_retry_default=0
wait_time_before_retry=${wait_time_before_retry_default}
increase_wait_time_before_retry_exponentially_default=no
increase_wait_time_before_retry_exponentially=${increase_wait_time_before_retry_exponentially_default}
retry_on_error_default=no
retry_on_error=${retry_on_error_default}
command_to_run=()

function print_command_line_help () {
    local defaults="Default values:"
    local new_line_indent="\n\t"
    local script_name
    script_name="$( basename "${BASH_SOURCE[0]}" )"
    local cli_format="Command line format:${new_line_indent}${script_name}"

    cli_format="${cli_format} [--timeout=<number of seconds>]"
    defaults="${defaults}${new_line_indent}timeout: ${timeout_default} (i.e., ${timeout_default} seconds)"

    cli_format="${cli_format} [--increase-timeout-exponentially=<yes|no>]"
    defaults="${defaults}${new_line_indent}increase-timeout-exponentially: ${increase_timeout_exponentially_default}"

    cli_format="${cli_format} [--max-tries=<number>]"
    defaults="${defaults}${new_line_indent}max-tries: ${max_tries_default}"

    cli_format="${cli_format} [--wait-time-before-retry=<number of seconds>]"
    defaults="${defaults}${new_line_indent}wait-time-before-retry: ${wait_time_before_retry_default} (i.e., ${wait_time_before_retry_default} seconds)"

    cli_format="${cli_format} [--increase-wait-time-before-retry-exponentially=<yes|no>]"
    defaults="${defaults}${new_line_indent}increase-wait-time-before-retry-exponentially: ${increase_wait_time_before_retry_exponentially_default}"

    cli_format="${cli_format} [--retry-on-error=<yes|no>]"
    defaults="${defaults}${new_line_indent}retry-on-error: ${retry_on_error_default}"

    cli_format="${cli_format} [--help]"

    cli_format="${cli_format} -- <command_to_run>"

    echo -e "${cli_format}"
    echo -e "${defaults}"
}

function parse_command_line_arguments () {
    local all_args="$*"
    local is_inside_command_to_run=no
    for arg in "$@"; do
        shift
        case "${arg}" in
            --timeout=*)
                    timeout="${arg#*=}"
                    ;;
            --increase-timeout-exponentially=*)
                    increase_timeout_exponentially="${arg#*=}"
                    ;;
            --max-tries=*)
                    max_tries="${arg#*=}"
                    ;;
            --wait-time-before-retry=*)
                    wait_time_before_retry="${arg#*=}"
                    ;;
            --increase-wait-time-before-retry-exponentially=*)
                    increase_wait_time_before_retry_exponentially="${arg#*=}"
                    ;;
            --retry-on-error=*)
                    retry_on_error="${arg#*=}"
                    ;;
            '--')
                    if [ "${is_inside_command_to_run}" = "no" ]; then
                        is_inside_command_to_run=yes
                    else
                        command_to_run+=("${arg}")
                    fi
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

    echo "timeout: ${timeout}"
    echo "increase_timeout_exponentially: ${increase_timeout_exponentially}"
    echo "max_tries: ${max_tries}"
    echo "wait_time_before_retry: ${wait_time_before_retry}"
    echo "increase_wait_time_before_retry_exponentially: ${increase_wait_time_before_retry_exponentially}"
    echo "retry_on_error: ${retry_on_error}"
    echo "command_to_run: \`${command_to_run[*]}'"
}

function main () {
    parse_command_line_arguments "$@"

    local try_count=0
    local current_timeout="${timeout}"
    local current_wait_time_before_retry="${wait_time_before_retry}"
    while [[ ${try_count} -lt ${max_tries} ]]; do
        ((++try_count))

        if [ "${increase_timeout_exponentially}" = "yes" ] && [ "${try_count}" -ne "1" ]; then
            current_timeout=$((current_timeout * 2))
        fi

        if [ "${wait_time_before_retry}" -ne "0" ] && [ "${try_count}" -ne "1" ]; then
            if [ "${increase_wait_time_before_retry_exponentially}" = "yes" ] && [ "${try_count}" -gt "2" ]; then
                current_wait_time_before_retry=$((current_wait_time_before_retry * 2))
            fi
            echo "Sleeping ${current_wait_time_before_retry} seconds before next try ..."
            sleep "${current_wait_time_before_retry}"
        fi

        echo "Running \`${command_to_run[*]}' (try ${try_count} out of ${max_tries}, current timeout: ${current_timeout} seconds) ..."
        set +e
        if [ "${current_timeout}" -eq "0" ]; then
            "${command_to_run[@]}"
        else
            timeout "${current_timeout}" "${command_to_run[@]}"
        fi
        exit_code=$?
        set -e
        if [ "${exit_code}" -eq "0" ]; then
            echo "\`${command_to_run[*]}' (try ${try_count} out of ${max_tries}) finished successfully"
            break
        fi

        if [ "${current_timeout}" -ne "0" ]; then
            # timeout returns 124 when the time limit is reached
            # also by default timeout sends SIGTERM to monitored process which results in exit code 143
            if [ "${exit_code}" -eq "124" ] || [ "${exit_code}" -eq "143" ]; then
                echo "\`${command_to_run[*]}' (try ${try_count} out of ${max_tries}, current timeout: ${current_timeout} seconds) timed out"
                continue
            fi
        fi

        echo "\`${command_to_run[*]}' (try ${try_count} out of max ${max_tries}, current timeout: ${current_timeout} seconds) exited with an error code ${exit_code}"
        if [ "${retry_on_error}" = "yes" ]; then
            continue
        fi

        break
    done

    exit "${exit_code}"
}

main "$@"
