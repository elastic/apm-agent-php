#!/usr/bin/env bash

# shellcheck disable=SC1091
source /usr/local/bin/bash_standard_lib.sh

# Make sure list of PHP versions supported by the Elastic APM PHP Agent is in sync.
# See the comment in .ci/shared.sh
DOCKER_IMAGES="alpine:3.11
centos:centos7
composer:1.10.10
php:7.2-fpm
php:7.3-fpm
php:7.4-fpm
php:8.0-fpm
php:8.1-fpm
php:8.2-fpm
ruby:2.7.1-alpine3.12
ubuntu:20.04
"
if [ -x "$(command -v docker)" ]; then
  for di in ${DOCKER_IMAGES}
  do
  (retry 2 docker pull "${di}") || echo "Error pulling ${di} Docker image, we continue"
  done

  # Make sure list of PHP versions supported by the Elastic APM PHP Agent is in sync.
  # See the comment in .ci/shared.sh
  for version in 7.2 7.3 7.4 8.0 8.1 8.2
  do
    PHP_VERSION=${version} make -f .ci/Makefile prepare || true
    DOCKERFILE=Dockerfile.alpine PHP_VERSION=${version} make -f .ci/Makefile prepare || true
    PHP_VERSION=${version} make -C packaging build-docker-images || true
  done
fi
