ARG PHP_VERSION=7.2
FROM php:${PHP_VERSION}-fpm

COPY --from=composer:2.3.5 /usr/bin/composer /usr/bin/composer

# sh: 1: ps: not found
# sh: 1: git: not found
# the zip extension and unzip command are both missing, skipping.
RUN apt-get -qq update \
 && apt-get -qq install -y dpkg-sig gnupg gnupg2 git procps zlib1g-dev libzip-dev wget unzip rsyslog --no-install-recommends \
 && rm -rf /var/lib/apt/lists/*

ENV TYPE=deb
ENV VERSION=
ENV GITHUB_RELEASES_URL=
COPY entrypoint.sh /bin
WORKDIR /src

ENTRYPOINT ["/bin/entrypoint.sh"]
