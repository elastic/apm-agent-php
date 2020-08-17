ARG PHP_VERSION=7.2
FROM php:${PHP_VERSION}-fpm

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/bin --filename=composer --version=1.10.10 \
 && php -r "unlink('composer-setup.php');"

# sh: 1: ps: not found
# sh: 1: git: not found
# the zip extension and unzip command are both missing, skipping.
RUN apt-get -qq update \
 && apt-get -qq install -y git procps zlib1g-dev libzip-dev unzip --no-install-recommends \
 && rm -rf /var/lib/apt/lists/*

ENV TYPE=deb
COPY entrypoint.sh /bin
WORKDIR /src

ENTRYPOINT ["/bin/entrypoint.sh"]