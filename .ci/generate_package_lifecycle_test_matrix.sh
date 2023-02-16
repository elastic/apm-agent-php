#!/usr/bin/env bash
set -e

function earliestSupportedPhpVersion () {
    echo "${ELASTIC_APM_PHP_TESTS_SUPPORTED_PHP_VERSIONS[0]}"
}

function latestSupportedPhpVersion () {
    echo "${ELASTIC_APM_PHP_TESTS_SUPPORTED_PHP_VERSIONS[-1]}"
}

function appendAgentSyslogLogLevel () {
    local rowSoFar="$1"
    local logLevel="$2"
    echo "${rowSoFar},agent_syslog_level=${logLevel}"
}

function appendTestsGroupVariants () {
    local rowSoFar="$1"
    local nextFunction="${2:-echo}"
    local -a nextFunctionArgs=( "${@:3}" )
    for testsGroup in "${ELASTIC_APM_PHP_TESTS_LEAF_GROUPS_SHORT_NAMES[@]}"
    do
        ${nextFunction} "${rowSoFar},${testsGroup}" "${nextFunctionArgs[@]}"
    done
}

function appendAppHostKindVariants () {
    local rowSoFar="$1"
    local nextFunction="${2:-echo}"
    local -a nextFunctionArgs=( "${@:3}" )
    for appendAppHostKindShortName in "${ELASTIC_APM_PHP_TESTS_APP_CODE_HOST_LEAF_KINDS_SHORT_NAMES[@]}" ; do
        ${nextFunction} "${rowSoFar},${appendAppHostKindShortName}" "${nextFunctionArgs[@]}"
    done
}

function generateLifecycleOnProdServerRows () {
    #
    # Lifecycle tests for <app_server> (only for deb linuxDistro and http appHostKind)
    #
    local linuxPackageType=deb
    local appHostKindShortName=http
    for phpVersion in "${ELASTIC_APM_PHP_TESTS_SUPPORTED_PHP_VERSIONS[@]}" ; do
        for prodAppServer in apache fpm ; do
            local testingType=lifecycle-${prodAppServer}
            appendTestsGroupVariants "${phpVersion},${linuxPackageType},${testingType},${appHostKindShortName}"
        done
    done
}

function generateLifecycleWithIncreasedLogLevelRows () {
    local testingType=lifecycle
    local phpVersion

    phpVersion=$(earliestSupportedPhpVersion)
    local linuxPackageType=apk
    appendAppHostKindVariants "${phpVersion},${linuxPackageType},${testingType}" appendTestsGroupVariants appendAgentSyslogLogLevel DEBUG

    phpVersion=$(latestSupportedPhpVersion)
    local linuxPackageType=deb
    appendAppHostKindVariants "${phpVersion},${linuxPackageType},${testingType}" appendTestsGroupVariants appendAgentSyslogLogLevel TRACE
}

function generateLifecycleTarPackageRows () {
    local linuxPackageType=tar
    local appHostKindShortName=all
    local testsGroup=smoke
    for phpVersion in "${ELASTIC_APM_PHP_TESTS_SUPPORTED_PHP_VERSIONS[@]}" ; do
        echo "${phpVersion},${linuxPackageType},${testingType},${appHostKindShortName},${testsGroup}"
    done
}

function generateLifecycleRows () {
    generateLifecycleOnProdServerRows
    #
    # Lifecycle tests
    #
    local testingType=lifecycle
    for phpVersion in "${ELASTIC_APM_PHP_TESTS_SUPPORTED_PHP_VERSIONS[@]}" ; do
        for linuxPackageType in "${ELASTIC_APM_PHP_TESTS_SUPPORTED_LINUX_NATIVE_PACKAGE_TYPES[@]}" ; do
            appendAppHostKindVariants "${phpVersion},${linuxPackageType},${testingType}" appendTestsGroupVariants
        done
    done

    generateLifecycleWithIncreasedLogLevelRows
    generateLifecycleTarPackageRows
}

function generatePhpUpgradeRows () {
    #
    # PHP upgrade tests (only for rpm Linux distro)
    #
    local phpVersion
    phpVersion="$(earliestSupportedPhpVersion)"
    local linuxPackageType=rpm
    local testingType=php-upgrade
    local appHostKindShortName=all
    local testsGroup=smoke
    echo "${phpVersion},${linuxPackageType},${testingType},${appHostKindShortName},${testsGroup}"
}

function generateAgentUpgradeRows () {
    #
    # Agent upgrade tests
    #
    local testingType=agent-upgrade
    local appHostKindShortName=all
    local testsGroup=smoke
    for phpVersion in 7.4 "$(latestSupportedPhpVersion)" ; do
        for linuxPackageType in deb rpm ; do
            echo "${phpVersion},${linuxPackageType},${testingType},${appHostKindShortName},${testsGroup}"
        done
    done
}

function main () {
    this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
    this_script_dir="$( realpath "${this_script_dir}" )"
    source "${this_script_dir}/shared.sh"

    generatePhpUpgradeRows

    generateAgentUpgradeRows

    generateLifecycleRows
}

main
