#!/usr/bin/env bash

################################################################################
############################ GLOBAL VARIABLES ##################################
################################################################################
PHP_AGENT_DIR=/opt/elastic/apm-agent-php
EXTENSION_FILE_PATH="${PHP_AGENT_DIR}/extensions/elastic_apm.so"
BOOTSTRAP_FILE_PATH="${PHP_AGENT_DIR}/src/bootstrap_php_part.php"
BACKUP_EXTENSION=".agent.bck"

################################################################################
########################## FUNCTION CALLS BELOW ################################
################################################################################

################################################################################
#### Function php_command ######################################################
function php_command() {
    PHP_BIN=$(command -v php)
    ${PHP_BIN} -d memory_limit=128M "$@"
}

################################################################################
#### Function php_ini_file_path ################################################
function php_ini_file_path() {
    php_command -i \
        | grep 'Configuration File (php.ini) Path =>' \
        | sed -e 's#Configuration File (php.ini) Path =>##g' \
        | head -n 1 \
        | awk '{print $1}'
}

################################################################################
#### Function is_extension_installed ###########################################
function is_extension_installed() {
    php_command -m | grep -q 'elastic'
}

################################################################################
#### Function add_extension_configuration_to_file ##############################
function add_extension_configuration_to_file() {
    cp -fa "$1" "${1}${BACKUP_EXTENSION}"
    tee -a "$1" <<EOF
; THIS IS AN AUTO-GENERATED FILE by the Elastic PHP agent post-install.sh script
extension=${EXTENSION_FILE_PATH}
elastic_apm.bootstrap_php_part_file=${BOOTSTRAP_FILE_PATH}
; END OF AUTO-GENERATED
EOF
}

################################################################################
############################### MAIN ###########################################
################################################################################
echo 'Installing Elastic PHP agent'
PHP_INI_FILE_PATH="$(php_ini_file_path)/php.ini"
if [ -e "${PHP_INI_FILE_PATH}" ] ; then
    if grep -q "${EXTENSION_FILE_PATH}" "${PHP_INI_FILE_PATH}" ; then
        echo '  extension configuration already exists for the Elastic PHP agent.'
        echo '  skipping ... '
    else
        add_extension_configuration_to_file "${PHP_INI_FILE_PATH}"
    fi
else
    add_extension_configuration_to_file "${PHP_INI_FILE_PATH}"
fi

if is_extension_installed ; then
    echo 'Extension enabled successfully for Elastic PHP agent'
else
    echo 'Failed enabling Elastic PHP agent extension'
    if [ -e "${PHP_INI_FILE_PATH}${BACKUP_EXTENSION}" ] ; then
        echo "Reverted changes in the file ${PHP_INI_FILE_PATH}"
        mv -f "${PHP_INI_FILE_PATH}${BACKUP_EXTENSION}" "${PHP_INI_FILE_PATH}"
    fi
    echo 'Set up the Agent manually as explained in:'
    echo 'https://github.com/elastic/apm-agent-php/blob/master/docs/setup.asciidoc'
fi