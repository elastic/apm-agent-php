#!/usr/bin/env bash
set -xe

thisScriptDir="$( dirname "${BASH_SOURCE[0]}" )"
thisScriptDir="$( realpath "${thisScriptDir}" )"

docker-compose -f "${thisScriptDir}/docker-compose_external_services_for_component_tests.yml" down -v --remove-orphans
