#!/usr/bin/env bash
set -e

## To be implemented, so far it prints the file with the static entries
cat << EOF
7.2,apk,component-testing,run_component_tests_http
7.3,apk,component-testing,run_component_tests_http
7.4,apk,component-testing,run_component_tests_http
8.0,apk,component-testing,run_component_tests_http
7.2,deb,component-testing,run_component_tests_http
7.3,deb,component-testing,run_component_tests_http
7.4,deb,component-testing,run_component_tests_http
8.0,deb,component-testing,run_component_tests_http
7.2,rpm,component-testing,run_component_tests_http
7.3,rpm,component-testing,run_component_tests_http
7.4,rpm,component-testing,run_component_tests_http
8.0,rpm,component-testing,run_component_tests_http
7.2,tar,component-testing,run_component_tests_http
7.3,tar,component-testing,run_component_tests_http
7.4,tar,component-testing,run_component_tests_http
8.0,tar,component-testing,run_component_tests_http
7.2,apk,component-testing,run_component_tests_cli
7.3,apk,component-testing,run_component_tests_cli
7.4,apk,component-testing,run_component_tests_cli
8.0,apk,component-testing,run_component_tests_cli
7.2,deb,component-testing,run_component_tests_cli
7.3,deb,component-testing,run_component_tests_cli
7.4,deb,component-testing,run_component_tests_cli
8.0,deb,component-testing,run_component_tests_cli
7.2,rpm,component-testing,run_component_tests_cli
7.3,rpm,component-testing,run_component_tests_cli
7.4,rpm,component-testing,run_component_tests_cli
8.0,rpm,component-testing,run_component_tests_cli
7.2,tar,component-testing,run_component_tests_cli
7.3,tar,component-testing,run_component_tests_cli
7.4,tar,component-testing,run_component_tests_cli
8.0,tar,component-testing,run_component_tests_cli
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
