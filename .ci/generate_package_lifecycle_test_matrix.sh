#!/usr/bin/env bash
set -e

# Make sure list of PHP versions supported by the Elastic APM PHP Agent is in sync
# - generate_package_lifecycle_test_matrix.sh
# - Jenkinsfile (the list appears in Jenkinsfile more than once - search for "list of PHP versions")
supportedPhpVersions=(7.2 7.3 7.4 8.0 8.1)

function echoWithSuffixVariantsFromComponentTestsAppHostKind () {
    rowSoFar="$1"
    for componentTestsAppHostKind in http cli
    do
        echo "${rowSoFar},${componentTestsAppHostKind}"
    done
}

#
# Lifecycle tests
#
testingType=lifecycle
for phpVersion in "${supportedPhpVersions[@]}"
do
    for linuxPackageType in apk deb rpm tar
    do
        echoWithSuffixVariantsFromComponentTestsAppHostKind "${phpVersion},${linuxPackageType},${testingType}"
    done
done

#
# Lifecycle tests for <app_server> (only for deb linuxDistro and http componentTestsAppHostKind)
#
linuxPackageType=deb
componentTestsAppHostKind=http
for phpVersion in "${supportedPhpVersions[@]}"
do
    for appServer in apache fpm
    do
        testingType=lifecycle-${appServer}
        echo "${phpVersion},${linuxPackageType},${testingType},${componentTestsAppHostKind}"
    done
done

#
# PHP upgrade tests (only for rpm Linux distro)
#
phpVersion=7.2
linuxPackageType=rpm
testingType=php-upgrade
echoWithSuffixVariantsFromComponentTestsAppHostKind "${phpVersion},${linuxPackageType},${testingType}"

#
# Agent upgrade tests
#
testingType=agent-upgrade
for phpVersion in 7.4 8.1
do
    for linuxPackageType in deb rpm
    do
        echoWithSuffixVariantsFromComponentTestsAppHostKind "${phpVersion},${linuxPackageType},${testingType}"
    done
done
