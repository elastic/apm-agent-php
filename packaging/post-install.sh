#!/usr/bin/env bash

###########
# GLOBALS #
###########
PHP_AGENT_DIR=/opt/elastic/apm-agent-php
EXTENSION_FILE_PATH="${PHP_AGENT_DIR}/extensions/elastic_apm.so"
BOOTSTRAP_FILE_PATH="${PHP_AGENT_DIR}/src/bootstrap_php_part.php"

################################################################################
########################## FUNCTION CALLS BELOW ################################
################################################################################

################################################################################
#### Function php_ini_path #####################################################
function php_ini_path() {
    PHP_BIN=$(command -v php)
    ${PHP_BIN} -d memory_limit=128M -i \
        | grep 'Configuration File (php.ini) Path =>' \
        | sed -e 's#Configuration File (php.ini) Path =>##g' \
        | head -n 1 \
        | awk '{print $1}'
}

################################################################################
#### Function add_extension_configuration_to_file ##############################
function add_extension_configuration_to_file() {
    echo '  Extension configuration has just been added for the Elastic PHP agent.'
    tee -a "$@" <<EOF
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
PHP_INI_FILE="$(php_ini_path)/php.ini"
if [ -e "${PHP_INI_FILE}" ] ; then
    if grep -q "${EXTENSION_FILE_PATH}" "${PHP_INI_FILE}" ; then
        echo '  extension configuration already exists for the Elastic PHP agent.'
        echo '  skipping ... '
    else
        add_extension_configuration_to_file "${PHP_INI_FILE}"
    fi
else
    add_extension_configuration_to_file "${PHP_INI_FILE}"
fi
