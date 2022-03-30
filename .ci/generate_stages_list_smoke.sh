#!/usr/bin/env bash
set -e

#
# install-testing
#
for phpVersion in 7.2 7.3 7.4 8.0 8.1
do
    for linuxDistro in apk deb rpm tar
    do
        echo ${phpVersion},${linuxDistro},install-testing
    done
done

