#!/usr/bin/env bash
set -e

function isValueInArray () {
    # The first argument is the element that should be in array
    local valueToCheck="$1"
    # The rest of the arguments is the array
    local -a array=( "${@:2}" )

    for valueInArray in "${array[@]}"; do
        if [ "${valueToCheck}" == "${valueInArray}" ] ; then
            echo "true"
            return
        fi
    done
    echo "false"
}

function assertValueIsInArray () {
    isValueInArrayRetVal=$(isValueInArray "$@")
    if [ "${isValueInArrayRetVal}" != "true" ] ; then
        exit 1
    fi
}

function convertComponentTestsAppHostKindShortNameToLongName () {
    local shortName="$1"
    case "${shortName}" in
        'cli')
                echo "CLI_script"
                return
                ;;
        'http')
                echo "Builtin_HTTP_server"
                return
                ;;
        *)
                echo "Unknown component tests short app code host kind name: \`${shortName}'"
                exit 1
                ;;
    esac
}

function convertComponentTestsGroupShortNameToLongName () {
    local shortName="$1"
    case "${shortName}" in
        'no_ext_svc')
                echo "does_not_require_external_services"
                return
                ;;
        'with_ext_svc')
                echo "requires_external_services"
                return
                ;;
        *)
                echo "Unknown component tests short group name: \`${shortName}'"
                exit 1
                ;;
    esac
}

function main () {
    this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
    this_script_dir="$( realpath "${this_script_dir}" )"
    source "${this_script_dir}/shared.sh"

    #
    # Expected format (see generate_package_lifecycle_test_matrix.sh)
    #
    #   phpVersion,linuxPackageType,testingType,componentTestsAppHostKindShortName,componentTestsGroup
    #   [0]        [1]              [2]         [3]                                [4]
    #
    local matrixRowAsString="$1"
    if [ -z "${matrixRowAsString}" ] ; then
        echo "The first argument (generated matrix row) which mandatory is missing"
        exit 1
    fi

    local matrixRowAsArray
    IFS=',' read -ra matrixRowAsArray <<< "${matrixRowAsString}"

    local phpVersion=${matrixRowAsArray[0]}
    assertValueIsInArray "${phpVersion}" "${ELASTIC_APM_PHP_TESTS_SUPPORTED_PHP_VERSIONS[@]}"

    local linuxPackageType=${matrixRowAsArray[1]}
    assertValueIsInArray "${linuxPackageType}" "${ELASTIC_APM_PHP_TESTS_SUPPORTED_LINUX_PACKAGE_TYPES[@]}"

    local testingType=${matrixRowAsArray[2]}
    local testingTypes=(lifecycle lifecycle-apache lifecycle-fpm php-upgrade agent-upgrade)
    assertValueIsInArray "${testingType}" "${testingTypes[@]}"

    local componentTestsAppHostKindShortName=${matrixRowAsArray[3]}
    export ELASTIC_APM_PHP_TESTS_APP_CODE_HOST_KIND
    ELASTIC_APM_PHP_TESTS_APP_CODE_HOST_KIND=$(convertComponentTestsAppHostKindShortNameToLongName "${componentTestsAppHostKindShortName}")

    local componentTestsGroupShortName=${matrixRowAsArray[4]}
    export ELASTIC_APM_PHP_TESTS_GROUP
    ELASTIC_APM_PHP_TESTS_GROUP=$(convertComponentTestsGroupShortNameToLongName "${componentTestsGroupShortName}")
}

main "$@"
