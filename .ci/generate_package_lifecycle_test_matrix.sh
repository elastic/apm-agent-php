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
    for componentTestsGroup in "${ELASTIC_APM_PHP_TESTS_GROUPS_SHORT_NAMES[@]}"
    do
        ${nextFunction} "${rowSoFar},${componentTestsGroup}" "${nextFunctionArgs[@]}"
    done
}

function appendAppHostKindVariants () {
    local rowSoFar="$1"
    local nextFunction="${2:-echo}"
    local -a nextFunctionArgs=( "${@:3}" )
    for componentTestsAppHostKindShortName in "${ELASTIC_APM_PHP_TESTS_APP_CODE_HOST_KINDS_SHORT_NAMES[@]}" ; do
        ${nextFunction} "${rowSoFar},${componentTestsAppHostKindShortName}" "${nextFunctionArgs[@]}"
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

function generateLifecycleRows () {
    #
    # Lifecycle tests
    #
    local testingType=lifecycle
    for phpVersion in "${ELASTIC_APM_PHP_TESTS_SUPPORTED_PHP_VERSIONS[@]}" ; do
        for linuxPackageType in "${ELASTIC_APM_PHP_TESTS_SUPPORTED_LINUX_PACKAGE_TYPES[@]}" ; do
            appendAppHostKindVariants "${phpVersion},${linuxPackageType},${testingType}" appendTestsGroupVariants
        done
    done
}

function generateLifecycleOnProdServerRows () {
    #
    # Lifecycle tests for <app_server> (only for deb linuxDistro and http componentTestsAppHostKind)
    #
    local linuxPackageType=deb
    local componentTestsAppHostKindShortName=http
    for phpVersion in "${ELASTIC_APM_PHP_TESTS_SUPPORTED_PHP_VERSIONS[@]}" ; do
        for prodAppServer in apache fpm ; do
            local testingType=lifecycle-${prodAppServer}
            appendTestsGroupVariants "${phpVersion},${linuxPackageType},${testingType},${componentTestsAppHostKindShortName}"
        done
    done
}

function generatePhpUpgradeRows () {
    #
    # PHP upgrade tests (only for rpm Linux distro)
    #
    local phpVersion
    phpVersion="$(earliestSupportedPhpVersion)"
    local linuxPackageType=rpm
    local testingType=php-upgrade
    echo "${phpVersion},${linuxPackageType},${testingType}"
}

function generateAgentUpgradeRows () {
    #
    # Agent upgrade tests
    #
    local testingType=agent-upgrade
    for phpVersion in 7.4 "$(latestSupportedPhpVersion)" ; do
        for linuxPackageType in deb rpm ; do
            appendAppHostKindVariants "${phpVersion},${linuxPackageType},${testingType}" appendTestsGroupVariants
        done
    done
}

function main () {
    this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
    this_script_dir="$( realpath "${this_script_dir}" )"
    source "${this_script_dir}/shared.sh"

    generateAgentUpgradeRows

    generateLifecycleRows
    generateLifecycleOnProdServerRows
    generateLifecycleWithIncreasedLogLevelRows

    generatePhpUpgradeRows
}

main
