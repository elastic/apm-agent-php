#!/usr/bin/env bash
set -xe

this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
this_script_dir="$( realpath "${this_script_dir}" )"
source "${this_script_dir}/../shared.sh"

(( EUID )) && { echo 'This script should be running with root privileges'; exit 1; }

linux_distro_name=$(detect_linux_distro)
if [ "${linux_distro_name}" = "" ]; then
    echo "Unknown Linux distro"
    cat /etc/*release || true
    exit 1
fi

temp_logrotate_config_file=/tmp/syslog_clearer_logrotate_config.txt
cp --force "${this_script_dir}/${linux_distro_name}/logrotate_config.txt" "${temp_logrotate_config_file}"
chmod "u=rw,o=,g=" "${temp_logrotate_config_file}"
logrotate --force --verbose "${temp_logrotate_config_file}"
rm -f /var/log/syslog.*
rm -f /var/log/messages.*
