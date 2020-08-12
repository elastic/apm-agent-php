#!/usr/bin/env bash

################################################################################
############################ GLOBAL VARIABLES ##################################
################################################################################
PHP_AGENT_DIR=/opt/elastic/apm-agent-php
EXTENSION_DIR="${PHP_AGENT_DIR}/extensions"
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
#### Function php_api ##########################################################
function php_api() {
    php_command -i \
        | grep 'PHP API' \
        | sed -e 's#.* =>##g' \
        | awk '{print $1}'
}

################################################################################
#### Function is_extension_enabled #############################################
function is_extension_enabled() {
    php_command -m | grep -q 'elastic'
}

################################################################################
#### Function add_extension_configuration_to_file ##############################
function add_extension_configuration_to_file() {
    tee -a "$1" <<EOF
; THIS IS AN AUTO-GENERATED FILE by the Elastic PHP agent post-install.sh script
extension=${EXTENSION_FILE_PATH}
elastic_apm.bootstrap_php_part_file=${BOOTSTRAP_FILE_PATH}
; END OF AUTO-GENERATED
EOF
}

################################################################################
#### Function manual_extension_agent_setup #####################################
function manual_extension_agent_setup() {
    echo 'Set up the Agent manually as explained in:'
    echo 'https://github.com/elastic/apm-agent-php/blob/master/docs/setup.asciidoc'
    if [ -e "${EXTENSION_FILE_PATH}" ] ; then
        echo 'Enable the extension by adding the following to your php.ini file:'
        echo "extension=${EXTENSION_FILE_PATH}"
        echo "elastic_apm.bootstrap_php_part_file=${BOOTSTRAP_FILE_PATH}"
    fi
}

################################################################################
#### Function get_extension_file ###############################################
function get_extension_file() {
    PHP_API=$(php_api)
    echo "${EXTENSION_DIR}/elastic_apm-${PHP_API}.so"
}

################################################################################
############################### MAIN ###########################################
################################################################################
echo 'Installing Elastic PHP agent'
EXTENSION_FILE_PATH=$(get_extension_file)
PHP_INI_FILE_PATH="$(php_ini_file_path)/php.ini"
if [ -e "${PHP_INI_FILE_PATH}" ] ; then
    if [ -e "${EXTENSION_FILE_PATH}" ] ; then
        if grep -q "${EXTENSION_FILE_PATH}" "${PHP_INI_FILE_PATH}" ; then
            echo '  extension configuration already exists for the Elastic PHP agent.'
            echo '  skipping ... '
        else
            cp -fa "${PHP_INI_FILE_PATH}" "${PHP_INI_FILE_PATH}${BACKUP_EXTENSION}"
            add_extension_configuration_to_file "${PHP_INI_FILE_PATH}"
        fi
    else
        PHP_API=$(php_api)
        echo 'Failed. Elastic PHP agent extension not supported for the current PHP API version.'
        echo "    PHP API => ${PHP_API}"
    fi
else
    add_extension_configuration_to_file "${PHP_INI_FILE_PATH}"
fi

if is_extension_enabled ; then
    echo 'Extension enabled successfully for Elastic PHP agent'
else
    echo 'Failed. Elastic PHP agent extension is not enabled'
    if [ -e "${PHP_INI_FILE_PATH}${BACKUP_EXTENSION}" ] ; then
        echo "Reverted changes in the file ${PHP_INI_FILE_PATH}"
        mv -f "${PHP_INI_FILE_PATH}${BACKUP_EXTENSION}" "${PHP_INI_FILE_PATH}"
    fi
    manual_extension_agent_setup
fi