#!/usr/bin/env bash

chmod -R 777 assets/
chmod -R 777 web/

#php yii migrate --interactive=0

/app/parent_container_docker_entrypoint.sh "$@"
