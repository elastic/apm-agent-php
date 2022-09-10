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
    sqlite-dev \
    unzip \
    wget

RUN echo "php -v: $(php -v)"
RUN echo "php -m: $(php -m)"

RUN docker-php-ext-install \
    pdo_mysql \
    mysqli \
    pdo_sqlite

RUN echo "php -v: $(php -v)"
RUN echo "php -m: $(php -m)"

COPY --from=composer:2.3.5 /usr/bin/composer /usr/bin/composer

ENV VERSION=
ENV GITHUB_RELEASES_URL=
COPY entrypoint.sh /bin
WORKDIR /src

ENTRYPOINT ["/bin/entrypoint.sh"]
