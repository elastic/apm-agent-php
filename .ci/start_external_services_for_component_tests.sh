#!/usr/bin/env bash
set -xe

if [ -z "${ELASTIC_APM_PHP_TESTS_EXTERNAL_SERVICES_ENV_VARS_ARE_SET}" ] ; then
    this_script_name="$( basename "${BASH_SOURCE[0]}" )"
    echo "env_vars_for_external_services_for_component_tests.sh should be sourced before running ${this_script_name}"
    exit 1
fi

thisScriptDir="$( dirname "${BASH_SOURCE[0]}" )"
thisScriptDir="$( realpath "${thisScriptDir}" )"

if [ -z "${ELASTIC_APM_PHP_TESTS_EXTERNAL_SERVICES_START_CMD}" ] ; then
    export ELASTIC_APM_PHP_TESTS_EXTERNAL_SERVICES_START_CMD="docker-compose -f ${thisScriptDir}/docker-compose_external_services_for_component_tests.yml up -d"
fi

# Give longer timeout to allow docker to download images, etc.
timeoutInMinutes=10
timeoutInSeconds=$((timeoutInMinutes*60))

# ELASTIC_APM_PHP_TESTS_EXTERNAL_SERVICES_START_CMD might contain multiple space separated arguments
# shellcheck disable=SC2086
"${thisScriptDir}/run_command_with_timeout_and_retries.sh" --timeout=${timeoutInSeconds} -- ${ELASTIC_APM_PHP_TESTS_EXTERNAL_SERVICES_START_CMD}
