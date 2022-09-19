#!/usr/bin/env bash
set -xe

thisScriptDir="$( dirname "${BASH_SOURCE[0]}" )"
thisScriptDir="$( realpath "${thisScriptDir}" )"

source "${thisScriptDir}/env_vars_for_external_services_for_component_tests.sh"

export ELASTIC_APM_PHP_TESTS_MYSQL_HOST=127.0.0.1
export ELASTIC_APM_PHP_TESTS_MYSQL_PORT=43306

dockerComposeCmdFilesPart="-f ${thisScriptDir}/docker-compose_external_services_for_component_tests.yml"
dockerComposeCmdFilesPart="${dockerComposeCmdFilesPart} -f ${thisScriptDir}/docker-compose_external_services_for_component_tests_on_host.yml"
export ELASTIC_APM_PHP_TESTS_EXTERNAL_SERVICES_START_CMD="docker-compose ${dockerComposeCmdFilesPart} up -d"
