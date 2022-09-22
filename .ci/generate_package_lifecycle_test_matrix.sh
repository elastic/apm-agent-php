#!/usr/bin/env bash
set -e

# Make sure list of PHP versions supported by the Elastic APM PHP Agent is in sync
# - generate_package_lifecycle_test_matrix.sh
# - Jenkinsfile (the list appears in Jenkinsfile more than once - search for "list of PHP versions")
supportedPhpVersions=(7.2 7.3 7.4 8.0 8.1)

function echoWithSuffixVariantsFromComponentTestsGroup () {
    local rowSoFar="$1"
    for componentTestsGroup in no_ext_svc with_ext_svc
    do
        echo "${rowSoFar},${componentTestsGroup}"
    done
}

function echoWithSuffixVariantsFromComponentTestsAppHostKind () {
    local rowSoFar="$1"
    for componentTestsAppHostKind in http cli
    do
        echoWithSuffixVariantsFromComponentTestsGroup "${rowSoFar},${componentTestsAppHostKind}"
    done
}

function generateLifecycleRows () {
    #
    # Lifecycle tests
    #
    local testingType=lifecycle
    for phpVersion in "${supportedPhpVersions[@]}"
    do
        for linuxPackageType in apk deb rpm tar
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
    local componentTestsAppHostKind=http
    for phpVersion in "${supportedPhpVersions[@]}"
    do
        for prodAppServer in apache fpm
        do
            local testingType=lifecycle-${prodAppServer}
            echoWithSuffixVariantsFromComponentTestsGroup "${phpVersion},${linuxPackageType},${testingType},${componentTestsAppHostKind}"
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
    generateLifecycleRows
    generateLifecycleOnProdServerRows
    generatePHPUpgradeRows
    generateAgentUpgradeRows
}

main
