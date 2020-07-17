FROM ruby:2.7.1-alpine3.12

RUN apk add --no-cache \
    alpine-sdk make cpio curl libarchive-tools make php-pear \
    python3 py3-virtualenv py3-setuptools py3-pip \
    rpm unzip xz git tar dpkg \
  && ln -sf python3 /usr/bin/python \
  && gem install --no-document fpm

ENTRYPOINT ["fpm"]
WORKDIR /src