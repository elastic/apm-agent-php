ARG PHP_VERSION=7.2
FROM php:${PHP_VERSION}-fpm

RUN apt-get -qq update \
 && apt-get -qq install -y \
    autoconf \
    build-essential \
    autoconf \
    curl \
    libcurl4-openssl-dev \
    procps \
    unzip \
    --no-install-recommends \
 && rm -rf /var/lib/apt/lists/*

FROM composer:1.10 as composer
COPY --from=composer /usr/bin/composer /usr/bin/

WORKDIR /app/src/ext

ENV REPORT_EXIT_STATUS=1
ENV TEST_PHP_DETAILED=1
ENV NO_INTERACTION=1
ENV TEST_PHP_JUNIT=/app/junit.xml

CMD phpize \
    && ./configure --enable-elastic_apm \
    && make clean \
    && make
