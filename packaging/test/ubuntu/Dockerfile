ARG PHP_VERSION=7.2
FROM php:${PHP_VERSION}-fpm

RUN php -r "copy('https://raw.githubusercontent.com/composer/getcomposer.org/8e98d817b4eac25e97cc9d34f875e739d24233cc/web/installer', 'composer-setup.php');" \
 && php -r "if (hash_file('sha384', 'composer-setup.php') === '572cb359b56ad9ae52f9c23d29d4b19a040af10d6635642e646a7caa7b96de717ce683bd797a92ce99e5929cc51e7d5f') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
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