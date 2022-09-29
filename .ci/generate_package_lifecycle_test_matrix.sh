#!/usr/bin/env bash
set -e


function echoWithSuffixVariantsFromComponentTestsGroup () {
    local rowSoFar="$1"
    for componentTestsGroup in "${ELASTIC_APM_PHP_TESTS_GROUPS_SHORT_NAMES[@]}"
    do
        echo "${rowSoFar},${componentTestsGroup}"
    done
}

function echoWithSuffixVariantsFromComponentTestsAppHostKind () {
    local rowSoFar="$1"
    for componentTestsAppHostKindShortName in "${ELASTIC_APM_PHP_TESTS_APP_CODE_HOST_KINDS_SHORT_NAMES[@]}"
    do
        echoWithSuffixVariantsFromComponentTestsGroup "${rowSoFar},${componentTestsAppHostKindShortName}"
    done
}

function generateLifecycleRows () {
    #
    # Lifecycle tests
    #
    local testingType=lifecycle
    for phpVersion in "${ELASTIC_APM_PHP_TESTS_SUPPORTED_PHP_VERSIONS[@]}"
    do
        for linuxPackageType in "${ELASTIC_APM_PHP_TESTS_SUPPORTED_LINUX_PACKAGE_TYPES[@]}"
        do
            echoWithSuffixVariantsFromComponentTestsAppHostKind "${phpVersion},${linuxPackageType},${testingType}"
        done
    done
}

function generateLifecycleOnProdServerRows () {
    #
    # Lifecycle tests for <app_server> (only for deb linuxDistro and http componentTestsAppHostKind)
    #
    local linuxPackageType=deb
    local componentTestsAppHostKindShortName=http
    for phpVersion in "${ELASTIC_APM_PHP_TESTS_SUPPORTED_PHP_VERSIONS[@]}"
    do
        for prodAppServer in apache fpm
        do
            local testingType=lifecycle-${prodAppServer}
            echoWithSuffixVariantsFromComponentTestsGroup "${phpVersion},${linuxPackageType},${testingType},${componentTestsAppHostKindShortName}"
        done
    done
}

function generatePHPUpgradeRows () {
    #
    # PHP upgrade tests (only for rpm Linux distro)
    #
    local phpVersion=7.2
    local linuxPackageType=rpm
    local testingType=php-upgrade
    echoWithSuffixVariantsFromComponentTestsAppHostKind "${phpVersion},${linuxPackageType},${testingType}"
}

function generateAgentUpgradeRows () {
    #
    # Agent upgrade tests
    #
    local testingType=agent-upgrade
    for phpVersion in 7.4 8.1
    do
        for linuxPackageType in deb rpm
        do
            echoWithSuffixVariantsFromComponentTestsAppHostKind "${phpVersion},${linuxPackageType},${testingType}"
        done
    done
}

function main () {
    this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
    this_script_dir="$( realpath "${this_script_dir}" )"
    source "${this_script_dir}/shared.sh"

    generateLifecycleRows
    generateLifecycleOnProdServerRows
    generatePHPUpgradeRows
    generateAgentUpgradeRows
}

main
