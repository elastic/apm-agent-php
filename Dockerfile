ARG PHP_VERSION=7.2
FROM wordpress:php${PHP_VERSION}-fpm-alpine

RUN apk --no-cache add build-base autoconf curl-dev

WORKDIR /app/src/ext

ENV REPORT_EXIT_STATUS=1
ENV TEST_PHP_DETAILED=1
ENV NO_INTERACTION=1
ENV TEST_PHP_JUNIT=/app/junit.xml

CMD phpize \
    && ./configure --enable-elasticapm \
    && make clean \
    && make
