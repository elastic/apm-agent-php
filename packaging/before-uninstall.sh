#!/usr/bin/env bash

######### Let's support alpine installations
PATH=${PATH}:/usr/local/bin

################################################################################
############################ GLOBAL VARIABLES ##################################
################################################################################
BACKUP_EXTENSION=".agent.uninstall.bck"

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
#### Function remove_extension_configuration_to_file ###########################
function remove_extension_configuration_to_file() {
    # remove lines from ; THIS IS AN AUTO-GENERATED FILE by the Elastic PHP agent post-install.sh script
    # then extension=
    # then elastic_apm.bootstrap_php_part_file=
    # then ; END OF AUTO-GENERATED
    echo "TBD"
}

################################################################################
#### Function get_extension_filename ###############################################
function get_extension_filename() {
    PHP_API=$(php_api)
    ## If alpine then add another suffix
    if grep -q -i alpine /etc/os-release; then
        SUFFIX=-alpine
    fi
    echo "elastic_apm-${PHP_API}${SUFFIX}.so"
}

################################################################################
############################### MAIN ###########################################
################################################################################
echo 'Uninstalling Elastic PHP agent'
EXTENSION_FILE_PATH=$(get_extension_filename)
PHP_INI_FILE_PATH="$(php_ini_file_path)/php.ini"
if [ -e "${PHP_INI_FILE_PATH}" ] ; then
    if [ -e "${EXTENSION_FILE_PATH}" ] ; then
        if grep -q "${EXTENSION_FILE_PATH}" "${PHP_INI_FILE_PATH}" ; then
            echo "${PHP_INI_FILE_PATH} has been configured with the Elastic PHP agent setup."
            cp -fa "${PHP_INI_FILE_PATH}" "${PHP_INI_FILE_PATH}${BACKUP_EXTENSION}"
            remove_extension_configuration_to_file "${PHP_INI_FILE_PATH}"
        else
            echo '  extension configuration does not exist for the Elastic PHP agent.'
            echo '  skipping ... '
        fi
    fi
else
    if [ -e "${EXTENSION_FILE_PATH}" ] ; then
        echo "${PHP_INI_FILE_PATH} has been disabled with the Elastic PHP agent setup."
        remove_extension_configuration_to_file "${PHP_INI_FILE_PATH}"
    fi
fi

if ! is_extension_enabled ; then
    echo 'Extension removed successfully for Elastic PHP agent.'
else
    echo 'Failed. Elastic PHP agent extension is still enabled.'
fi
