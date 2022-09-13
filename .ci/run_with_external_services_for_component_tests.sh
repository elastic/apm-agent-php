#!/usr/bin/env bash
set -xe

docker_compose_cmd_prefix="docker-compose -f .ci/docker-compose_external_services_for_component_tests.yml"

${docker_compose_cmd_prefix} up -d

export ELASTIC_APM_PHP_TESTS_MYSQL_HOST=127.0.0.1
export ELASTIC_APM_PHP_TESTS_MYSQL_PORT=43306
export ELASTIC_APM_PHP_TESTS_MYSQL_USER=root
export ELASTIC_APM_PHP_TESTS_MYSQL_PASSWORD=elastic-apm-php-component-tests-mysql-password
export ELASTIC_APM_PHP_TESTS_MYSQL_DB=ELASTIC_APM_PHP_COMPONENT_TESTS_DB

"$@"

${docker_compose_cmd_prefix} down -v --remove-orphans
