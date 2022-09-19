#!/usr/bin/env bash
set -xe

thisScriptDir="$( dirname "${BASH_SOURCE[0]}" )"
thisScriptDir="$( realpath "${thisScriptDir}" )"

function shouldPassEnvVarToDocker () {
    envVarNameToCheck="$1"

    # Pass all the environment variables whose names start with ELASTIC_APM_
    if [[ ${envVarNameToCheck} == "ELASTIC_APM_"* ]]; then
        echo "true"
    fi

    local envVarNamesToPassToDocker=(COMPONENT_TEST_SCRIPT GITHUB_RELEASES_URL PACKAGE TYPE VERSION)

    for envVarNameToPassToDocker in "${envVarNamesToPassToDocker[@]}"
    do
        if [ "${envVarNameToCheck}" == "${envVarNameToPassToDocker}" ] ; then
            echo "true"
        fi
    done

    echo "false"
}

function buildDockerEnvVarsCommandLinePart () {
    local -n result=$1
    result=()
    # Iterate over environment variables
    # The code is copied from https://stackoverflow.com/questions/25765282/bash-loop-through-variables-containing-pattern-in-name
    while IFS='=' read -r envVarName envVarValue ; do
        shouldPass=$(shouldPassEnvVarToDocker "${envVarName}")
        if [ "${shouldPass}" == "false" ] ; then
            continue
        fi
        echo "envVarValue: ${envVarValue}"
        # shellcheck disable=SC2116
        envVarValue=$(echo "${envVarValue}")
        echo "envVarValue: ${envVarValue}"
        result=("${result[@]}" -e "${envVarName}=${envVarValue}")
    done < <(env)
}

function doesTestsGroupNeedExternalServices () {
    if [ -z "${ELASTIC_APM_PHP_TESTS_COMPONENT_TESTS_GROUP}" ] ; then
        echo "true"
        return
    fi

    case "${ELASTIC_APM_PHP_TESTS_COMPONENT_TESTS_GROUP}" in
        'does_not_require_external_services')
                echo "false"
                return
                ;;
        'requires_external_services')
                echo "true"
                return
                ;;
        *)
                echo "Unknown tests group name: \`${ELASTIC_APM_PHP_TESTS_COMPONENT_TESTS_GROUP}\'"
                exit 1
                ;;
    esac
}

function main () {
    shouldStartExternalServices=$(doesTestsGroupNeedExternalServices)
    if [ "${shouldStartExternalServices}" == "true" ] ; then
        source "${thisScriptDir}/env_vars_for_external_services_for_component_tests.sh"
        "${thisScriptDir}/start_external_services_for_component_tests.sh"
    fi

    repoRootDir="$( realpath "${thisScriptDir}/.." )"

    buildDockerEnvVarsCommandLinePart envVarsCommandLine
    # shellcheck disable=SC2154 # envVarsCommandLine is assigned by buildDockerEnvVarsCommandLinePart
    docker run --rm -v "${repoRootDir}:/src" -w /src "${envVarsCommandLine[@]}" --network=elastic-apm-php-external-services-for-component-tests-net "$@"

    if [ "${shouldStartExternalServices}" == "true" ] ; then
        "${thisScriptDir}/stop_external_services_for_component_tests.sh"
    fi
}

main "$@"
