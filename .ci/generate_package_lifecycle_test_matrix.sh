#!/usr/bin/env bash
set -e

# Make sure list of PHP versions supported by the Elastic APM PHP Agent is in sync
# - generate_package_lifecycle_test_matrix.sh
# - Jenkinsfile (the list appears in Jenkinsfile more than once - search for "list of PHP versions")
supportedPhpVersions=(7.2 7.3 7.4 8.0 8.1)

#
# Lifecycle tests
#
testingType=lifecycle
for phpVersion in "${supportedPhpVersions[@]}"
do
    for linuxDistro in apk deb rpm tar
    do
        for componentTestsAppHostKind in http cli
        do
            echo ${phpVersion},${linuxDistro},${testingType},${componentTestsAppHostKind}
        done
    done
done

#
# Lifecycle tests for <app_server> (only for deb linuxDistro and http componentTestsAppHostKind)
#
linuxDistro=deb
componentTestsAppHostKind=http
for phpVersion in "${supportedPhpVersions[@]}"
do
    for appServer in apache fpm
    do
        testingType=lifecycle-${appServer}
        echo ${phpVersion},${linuxDistro},${testingType},${componentTestsAppHostKind}
    done
done

#
# PHP upgrade tests (only for rpm Linux distro)
#
testingType=php-upgrade
phpVersion=7.2
linuxDistro=rpm
for componentTestsAppHostKind in http cli
do
    echo ${phpVersion},${linuxDistro},${testingType},${componentTestsAppHostKind}
done

#
# Agent upgrade tests
#
testingType=agent-upgrade
for phpVersion in 7.4 8.1
do
    for linuxDistro in deb rpm
    do
        for componentTestsAppHostKind in http cli
        do
            echo ${phpVersion},${linuxDistro},${testingType},${componentTestsAppHostKind}
        done
    done
done
