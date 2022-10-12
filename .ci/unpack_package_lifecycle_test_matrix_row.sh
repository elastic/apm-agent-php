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
    local isValueInArrayRetVal
    isValueInArrayRetVal=$(isValueInArray "$@")
    if [ "${isValueInArrayRetVal}" != "true" ] ; then
        exit 1
    fi
}

function convertAppHostKindShortToLongName () {
    local shortName="$1"
    case "${shortName}" in
        'all')
                echo "all"
                return
                ;;
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

function convertTestsGroupShortToLongName () {
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
        'smoke')
                echo "smoke"
                return
                ;;
        *)
                echo "Unknown component tests short group name: \`${shortName}'"
                exit 1
                ;;
    esac
}

function unpackRowToEnvVars () {
    #
    # Expected format (see generate_package_lifecycle_test_matrix.sh)
    #
    #       phpVersion,linuxPackageType,testingType,appHostKindShortName,testsGroupShortName[,optionalTail]
    #       [0]        [1]              [2]         [3]                  [4]                  [5], [6] ...
    #
    local matrixRowAsString="$1"
    if [ -z "${matrixRowAsString}" ] ; then
        echo "The first argument (generated matrix row) which mandatory is missing"
        exit 1
    fi

    local matrixRowParts
    IFS=',' read -ra matrixRowParts <<< "${matrixRowAsString}"

    local phpVersion=${matrixRowParts[0]}
    assertValueIsInArray "${phpVersion}" "${ELASTIC_APM_PHP_TESTS_SUPPORTED_PHP_VERSIONS[@]}"

    local linuxPackageType=${matrixRowParts[1]}
    assertValueIsInArray "${linuxPackageType}" "${ELASTIC_APM_PHP_TESTS_SUPPORTED_LINUX_PACKAGE_TYPES[@]}"

    local testingType=${matrixRowParts[2]}
    local testingTypes=(lifecycle lifecycle-apache lifecycle-fpm php-upgrade agent-upgrade)
    assertValueIsInArray "${testingType}" "${testingTypes[@]}"

    if [ "${#matrixRowParts[@]}" -eq "3" ] ; then
        return
    fi

    local appHostKindShortName=${matrixRowParts[3]}
    export ELASTIC_APM_PHP_TESTS_APP_CODE_HOST_KIND
    ELASTIC_APM_PHP_TESTS_APP_CODE_HOST_KIND=$(convertAppHostKindShortToLongName "${appHostKindShortName}")

    local testsGroupShortName=${matrixRowParts[4]}
    export ELASTIC_APM_PHP_TESTS_GROUP
    ELASTIC_APM_PHP_TESTS_GROUP=$(convertTestsGroupShortToLongName "${testsGroupShortName}")

    for optionalPart in "${matrixRowParts[@]:5}" ; do
        IFS='=' read -ra keyValue <<< "${optionalPart}"
        unpackRowOptionalPartsToEnvVars "${keyValue[0]}" "${keyValue[1]}"
    done
}

function unpackRowOptionalPartsToEnvVars () {
    local key="$1"
    local value="$2"
    case "${key}" in
        'agent_syslog_level')
                export ELASTIC_APM_LOG_LEVEL_SYSLOG="${value}"
                ;;
        *)
                echo "Unknown optional part key: \`${key}' (value: \`${value}')"
                exit 1
                ;;
    esac
}

function main () {
    this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
    this_script_dir="$( realpath "${this_script_dir}" )"
    source "${this_script_dir}/shared.sh"

    unpackRowToEnvVars "$@"
}

main "$@"
