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
    # with elastic_apm.bootstrap_php_part_file=.+
    # then ; END OF AUTO-GENERATED by the Elastic PHP agent post-install.sh script
    HEADER='; THIS IS AN AUTO-GENERATED FILE by the Elastic PHP agent post-install.sh'
    FOOTER='; END OF AUTO-GENERATED by the Elastic PHP agent post-install.sh'
    EXTENSION='extension=.*'
    BOOTSTRAP='elastic_apm.bootstrap_php_part_file=.*'
    numberOfHeaderMatches=$(grep "${HEADER}" "$1" -c)
    numberOfExtensionMatches=$(grep "${EXTENSION}" "$1" -c)
    numberOfBootstrapMatches=$(grep "${BOOTSTRAP}" "$1" -c)
    numberOfFooterMatches=$(grep "${FOOTER}" "$1" -c)
    ## Delete only if there is only an automated installation in place.
    if [ "${numberOfHeaderMatches}" == "1" ] && [ "${numberOfExtensionMatches}" == "1" ] && [ "${numberOfBootstrapMatches}" == "1" ] && [ "${numberOfFooterMatches}" == "1" ] ; then
        TMP_FILE=/tmp/uninstall-apm-agent-pipeline
        grep "${HEADER}" "$1" -A 3 > ${TMP_FILE}
        footer=$(grep "${FOOTER}" -n ${TMP_FILE} | cut -f1 -d:)
        extension=$(grep "${EXTENSION}" -n ${TMP_FILE} | cut -f1 -d:)
        bootstrap=$(grep "${BOOTSTRAP}" -n ${TMP_FILE} | cut -f1 -d:)
        ## Ensure the automated installation in place uses the right format.
        if [ "${extension}" == "2" ] && [ "${bootstrap}" == "3" ] && [ "${footer}" == "4" ] ; then
            echo "$1 has been configured with the Elastic PHP agent setup, let's uninstalled it."
            headerLine=$(grep "${HEADER}" -n "$1" | cut -f1 -d:)
            extensionLine=$((headerLine + 1))
            bootstrapLine=$((headerLine + 2))
            footerLine=$((headerLine + 3))
            sed -i${BACKUP_EXTENSION} "${headerLine}d;${extensionLine}d;${bootstrapLine}d;${footerLine}d" "$1"
        else
            echo "$1 has been configured with the Elastic PHP agent setup. But it cannot be uninstalled automatically."
            manual_extension_agent_uninstallation
        fi
    else
        echo "$1 has been configured with the Elastic PHP agent setup. But it cannot be uninstalled automatically."
        manual_extension_agent_uninstallation
    fi
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
#### Function manual_extension_agent_uninstallation ############################
function manual_extension_agent_uninstallation() {
    echo 'Uninstall the Agent manually as explained in:'
    echo 'https://github.com/elastic/apm-agent-php/blob/master/docs/setup.asciidoc'
}

################################################################################
############################### MAIN ###########################################
################################################################################
echo 'Uninstalling Elastic PHP agent'
EXTENSION_FILENAME=$(get_extension_filename)
PHP_INI_FILE_PATH="$(php_ini_file_path)/php.ini"
if [ -e "${PHP_INI_FILE_PATH}" ] ; then
    if grep -q "${EXTENSION_FILENAME}" "${PHP_INI_FILE_PATH}" ; then
        remove_extension_configuration_to_file "${PHP_INI_FILE_PATH}"
    else
        echo '  extension configuration does not exist for the Elastic PHP agent.'
        echo '  skipping ... '
    fi
else
    echo 'No default php.ini file has been found.'
fi

if is_extension_enabled ; then
    echo 'Failed. Elastic PHP agent extension is still enabled.'
    if [ -e "${PHP_INI_FILE_PATH}${BACKUP_EXTENSION}" ] ; then
        echo "Reverted changes in the file ${PHP_INI_FILE_PATH}"
        mv -f "${PHP_INI_FILE_PATH}${BACKUP_EXTENSION}" "${PHP_INI_FILE_PATH}"
        echo "${PHP_INI_FILE_PATH} got some leftovers please delete the entries for the Elastic PHP agent manually"
    fi
else
    echo 'Extension has been removed successfully for Elastic PHP agent.'
fi
