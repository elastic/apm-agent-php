ARG PHP_VERSION=7.2
FROM php:${PHP_VERSION}-fpm

FROM composer:1.10 as composer
COPY --from=composer /usr/bin/composer /usr/bin/

# sh: 1: ps: not found
# sh: 1: git: not found
# the zip extension and unzip command are both missing, skipping.
RUN apt-get -qq update \
 && apt-get -qq install -y autoconf git procps zlib1g-dev libzip-dev unzip --no-install-recommends \
 && rm -rf /var/lib/apt/lists/*

ENV TYPE=deb
COPY entrypoint.sh /bin
WORKDIR /src

ENTRYPOINT ["/bin/entrypoint.sh"]