ARG PHP_VERSION=7.2
FROM php:${PHP_VERSION}-fpm-alpine

RUN apk update \
  && apk add \
        bash \
        curl \
        git \
        logrotate \
        perl-utils \
        procps \
        rsyslog \
        sqlite-dev \
        unzip \
        wget

ARG ADD_LIBUNWIND_DEPENDENCY=false
RUN if [[ "${ADD_LIBUNWIND_DEPENDENCY}" = "true" ]] ; then apk add libunwind; fi

RUN docker-php-ext-install \
        mysqli \
        pcntl \
        pdo_mysql \
        pdo_sqlite \
        posix

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Disable agent for auxiliary PHP processes to reduce noise in logs
ENV ELASTIC_APM_ENABLED=false

ENV VERSION=
ENV GITHUB_RELEASES_URL=
COPY entrypoint.sh /bin
WORKDIR /app

ENTRYPOINT ["/bin/entrypoint.sh"]
