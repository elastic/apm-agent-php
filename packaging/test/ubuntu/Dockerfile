ARG PHP_VERSION=7.2
FROM php:${PHP_VERSION}-fpm

RUN apt-get -qq update \
    && apt-get -qq -y --no-install-recommends install \
        dpkg-sig \
        gnupg \
        gnupg2 \
        git \
        libsqlite3-dev \
        libzip-dev \
        logrotate \
        procps \
        rsyslog \
        unzip \
        wget \
        zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
        mysqli \
        pcntl \
        pdo_mysql \
        pdo_sqlite \
        posix

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Disable agent for auxiliary PHP processes to reduce noise in logs
ENV ELASTIC_APM_ENABLED=false

ENV TYPE=deb
ENV VERSION=
ENV GITHUB_RELEASES_URL=
COPY entrypoint.sh /bin
WORKDIR /app

ENTRYPOINT ["/bin/entrypoint.sh"]
