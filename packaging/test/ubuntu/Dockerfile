ARG PHP_VERSION=7.2
FROM php:${PHP_VERSION}-fpm

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php -r "if (hash_file('sha384', 'composer-setup.php') === 'e5325b19b381bfd88ce90a5ddb7823406b2a38cff6bb704b0acc289a09c8128d4a8ce2bbafcd1fcbdc38666422fe2806') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
 && php composer-setup.php --install-dir=/usr/bin --filename=composer \
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