#!/usr/bin/env bash
set -e

## To be implemented, so far it prints the file with the static entries
cat << EOF
7.2,apk,lifecycle-testing
7.3,apk,lifecycle-testing
7.4,apk,lifecycle-testing
8.0,apk,lifecycle-testing
7.2,deb,lifecycle-testing
7.3,deb,lifecycle-testing
7.4,deb,lifecycle-testing
8.0,deb,lifecycle-testing
7.2,rpm,lifecycle-testing
7.3,rpm,lifecycle-testing
7.4,rpm,lifecycle-testing
8.0,rpm,lifecycle-testing
7.2,tar,lifecycle-testing
7.3,tar,lifecycle-testing
7.4,tar,lifecycle-testing
8.0,tar,lifecycle-testing
7.2,rpm,php-upgrade-testing
7.4,deb,agent-upgrade-testing
7.4,rpm,agent-upgrade-testing
7.2,deb,lifecycle-testing-in-apache
7.3,deb,lifecycle-testing-in-apache
7.4,deb,lifecycle-testing-in-apache
8.0,deb,lifecycle-testing-in-apache
7.2,deb,lifecycle-testing-in-fpm
7.3,deb,lifecycle-testing-in-fpm
7.4,deb,lifecycle-testing-in-fpm
8.0,deb,lifecycle-testing-in-fpm
EOF
