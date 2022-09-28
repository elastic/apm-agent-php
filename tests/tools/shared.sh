#!/usr/bin/env bash
set -xe

shared_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
shared_script_dir="$( realpath "${shared_script_dir}" )"
# shellcheck disable=SC2034 # repo_root_dir is used in script source-ing this script
repo_root_dir="$( realpath "${shared_script_dir}/../.." )"

function detect_linux_distro () {
    for distro_name in alpine ubuntu debian centos; do
        cat /etc/*release | grep -i "${distro_name}" &> /dev/null
        exitCode=$?
        if [ "${exitCode}" -eq 0 ]; then
            echo "${distro_name}"
            return
        fi
    done

    echo ""
}
