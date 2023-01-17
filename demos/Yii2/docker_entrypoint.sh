#!/usr/bin/env bash

set -xe

chmod -R +rw .

php yii migrate --interactive=0

set +xe
/app/parent_container_docker_entrypoint.sh "$@"
