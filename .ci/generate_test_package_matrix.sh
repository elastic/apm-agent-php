#!/usr/bin/env bash
set -e

#
# Component tests
#
for phpVersion in 7.2 7.3 7.4 8.0 8.1
do
    for linuxDistro in apk deb rpm tar
    do
        for componentTestsAppHostKind in http cli
        do
            echo ${phpVersion},${linuxDistro},component,${componentTestsAppHostKind}
        done
    done
done

#
# PHP upgrade tests
#
echo 7.2,rpm,php-upgrade

#
# Agent upgrade tests
#
for phpVersion in 7.4 8.1
do
    for linuxDistro in deb rpm
    do
        echo ${phpVersion},${linuxDistro},agent-upgrade
    done
done

#
# Lifecycle tests for <app_server>
#
for phpVersion in 7.2 7.3 7.4 8.0 8.1
do
    for linuxDistro in deb
    do
        for appServer in apache fpm
        do
            echo ${phpVersion},${linuxDistro},lifecycle-in-${appServer}
        done
    done
done

