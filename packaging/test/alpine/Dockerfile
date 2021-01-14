ARG PHP_VERSION=7.2
FROM php:${PHP_VERSION}-fpm-alpine

RUN apk update \
  && apk add \
    bash \
    curl \
    git \
    perl-utils \
    procps \
    rsyslog \
    unzip \
    wget

ARG COMPOSER_VERSION=1.10.10
COPY --from=composer:${COMPOSER_VERSION} /usr/bin/composer /usr/bin/composer

RUN php -v

ENV VERSION=
ENV GITHUB_RELEASES_URL=
COPY entrypoint.sh /bin
WORKDIR /src

ENTRYPOINT ["/bin/entrypoint.sh"]
