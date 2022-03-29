#!/usr/bin/env bash
set -e

#
# component-testing
#
for phpVersion in 7.2 7.3 7.4 8.0 8.1
do
    for linuxDistro in apk deb rpm tar
    do
        for componentTestsAppHostKind in http cli
        do
            echo ${phpVersion},${linuxDistro},component-testing,run_component_tests_${componentTestsAppHostKind}
        done
    done
done

#
# php-upgrade-testing
#
echo 7.2,rpm,php-upgrade-testing

#
# agent-upgrade-testing
#
for phpVersion in 7.4 8.1
do
    for linuxDistro in deb rpm
    do
        echo ${phpVersion},${linuxDistro},agent-upgrade-testing
    done
done

#
# lifecycle-testing-in-<app_server>
#
for phpVersion in 7.2 7.3 7.4 8.0 8.1
do
    for linuxDistro in deb
    do
        for appServer in apache fpm
        do
            echo ${phpVersion},${linuxDistro},lifecycle-testing-in-${appServer}
        done
    done
done

