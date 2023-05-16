FROM node:14.5.0-stretch-slim

RUN apt-get update -qq -y \
  && apt-get install -qq -y --no-install-recommends git \
  && rm -rf /var/lib/apt/lists/*

# Forced to use a previous version to group the releases by tags.
# See https://github.com/github-tools/github-release-notes/issues/279
RUN npm install github-release-notes@0.17.2 -g
WORKDIR /app

ENTRYPOINT [ "/app/.ci/docker/gren/entrypoint.sh" ]
