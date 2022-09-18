#!/usr/bin/env bash
set -xe

docker-compose -f .ci/docker-compose_external_services_for_component_tests.yml down -v --remove-orphans
