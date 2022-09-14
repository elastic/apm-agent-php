#!/usr/bin/env bash
set -e

#
# run-test-command-with-timeout.sh --timeout=<number of seconds> --max-tries=<number> --wait-time-before-retry=<number of seconds> --retry-on-error=<yes|no> -- <command_to_run>
#

timeout_default=10
timeout=${timeout_default}
max_tries_default=3
max_tries=${max_tries_default}
wait_time_before_retry_default=10
wait_time_before_retry=${wait_time_before_retry_default}
retry_on_error_default=yes
retry_on_error=${retry_on_error_default}
command_to_run=()

function print_command_line_help () {
    defaults="Default values:"
    new_line_indent="\n\t"
    script_name="$( basename "${BASH_SOURCE[0]}" )"
    cli_format="Command line format:${new_line_indent}${script_name}"

    cli_format="${cli_format} [--timeout=<number of seconds>]"
    defaults="${defaults}${new_line_indent}timeout: ${timeout_default} (i.e., ${timeout_default} seconds)"

    cli_format="${cli_format} [--max-tries=<number>]"
    defaults="${defaults}${new_line_indent}max-tries: ${max_tries_default}"

    cli_format="${cli_format} [--wait-time-before-retry=<number of seconds>]"
    defaults="${defaults}${new_line_indent}wait-time-before-retry: ${wait_time_before_retry_default} (i.e., ${wait_time_before_retry_default} seconds)"

    cli_format="${cli_format} [--retry-on-error=<yes|no>]"
    defaults="${defaults}${new_line_indent}retry-on-error: ${retry_on_error_default}"

    cli_format="${cli_format} [--help]"
    defaults="${defaults}${new_line_indent}retry-on-error: ${retry_on_error_default}"

    cli_format="${cli_format} -- <command_to_run>"

    echo -e "${cli_format}"
    echo -e "${defaults}"
}

function parse_command_line_arguments () {
    all_args="$*"
    is_inside_command_to_run=no
    for arg in "$@"; do
        shift
        case "${arg}" in
            --timeout=*)
                    timeout="${arg#*=}"
                    ;;
            --max-tries=*)
                    max_tries="${arg#*=}"
                    ;;
            --wait-time-before-retry=*)
                    wait_time_before_retry="${arg#*=}"
                    ;;
            --retry-on-error=*)
                    retry_on_error="${arg#*=}"
                    ;;
            '--')
                    is_inside_command_to_run=yes
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
    echo "max_tries: ${max_tries}"
    echo "wait_time_before_retry: ${wait_time_before_retry}"
    echo "retry_on_error: ${retry_on_error}"
    echo "command_to_run: \`${command_to_run[*]}'"
}

function main () {
    parse_command_line_arguments "$@"

    try_count=0
    while [[ ${try_count} -lt ${max_tries} ]]; do
        ((++try_count))
        echo "Running \`${command_to_run[*]}' (try ${try_count} out of ${max_tries}) ..."
        set +e
        # shellcheck disable=SC2086
        timeout "${timeout}" "${command_to_run[@]}"
        exit_code=$?
        set -e
        if [ "${exit_code}" -eq "0" ]; then
            echo "\`${command_to_run[*]}' (try ${try_count} out of ${max_tries}) finished successfully"
            break
        fi

        # timeout returns 124 when the time limit is reached
        if [ "${exit_code}" -eq "124" ]; then
            echo "\`${command_to_run[*]}' (try ${try_count} out of ${max_tries}) timed out"
            continue
        fi

        echo "\`${command_to_run[*]}' (try ${try_count} out of max ${max_tries}) exited with an error code ${exit_code}"
        if [ "${retry_on_error}" = "yes" ]; then
            continue
        fi
        exit "${exit_code}"
    done
}

main "$@"
