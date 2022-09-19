ARG PHP_VERSION=7.2
FROM php:${PHP_VERSION}-fpm

RUN echo "php -v: $(php -v)"
RUN echo "php -m: $(php -m)"

RUN apt-get -qq update \
    && apt-get -qq -y --no-install-recommends install \
        dpkg-sig \
        gnupg \
        gnupg2 \
        git \
        libsqlite3-dev \
        libzip-dev \
        procps \
        rsyslog \
        unzip \
        wget \
        zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
    pdo_mysql \
    mysqli \
    pdo_sqlite

RUN echo "php -v: $(php -v)"
RUN echo "php -m: $(php -m)"

COPY --from=composer:2.3.5 /usr/bin/composer /usr/bin/composer

ENV TYPE=deb
ENV VERSION=
ENV GITHUB_RELEASES_URL=
COPY entrypoint.sh /bin
WORKDIR /src

ENTRYPOINT ["/bin/entrypoint.sh"]
