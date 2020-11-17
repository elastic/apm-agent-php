#!/usr/bin/env bash

# shellcheck disable=SC1091
source /usr/local/bin/bash_standard_lib.sh

DOCKER_IMAGES="alpine:3.11
centos:centos7
php:7.2-fpm
php:7.3-fpm
php:7.4-fpm
php:8.0-rc-fpm
ruby:2.7.1-alpine3.12
"
if [ -x "$(command -v docker)" ]; then
  for di in ${DOCKER_IMAGES}
  do
  (retry 2 docker pull "${di}") || echo "Error pulling ${di} Docker image, we continue"
  done

  for version in 7.2 7.3 7.4 8.0-rc
  do
    PHP_VERSION=${version} make -f .ci/Makefile prepare || true
    DOCKERFILE=Dockerfile.alpine PHP_VERSION=${version} make -f .ci/Makefile prepare || true
    PHP_VERSION=${version} make -C packaging prepare || true
  done
fi
