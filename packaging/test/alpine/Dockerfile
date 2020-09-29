ARG PHP_VERSION=7.2
FROM php:${PHP_VERSION}-fpm-alpine

RUN apk update \
  && apk add \
    bash \
    curl \
    git \
    perl-utils \
    procps \
    unzip \
    wget

RUN php -r "copy('https://raw.githubusercontent.com/composer/getcomposer.org/baecae060ee7602a9908f2259f7460b737839972/web/installer', 'composer-setup.php');" \
 && php -r "if (hash_file('sha384', 'composer-setup.php') === '572cb359b56ad9ae52f9c23d29d4b19a040af10d6635642e646a7caa7b96de717ce683bd797a92ce99e5929cc51e7d5f') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
 && php composer-setup.php --install-dir=/usr/bin --filename=composer --version=1.10.10 \
 && php -r "unlink('composer-setup.php');"

RUN php -v

ENV VERSION=
ENV GITHUB_RELEASES_URL=
COPY entrypoint.sh /bin
WORKDIR /src

ENTRYPOINT ["/bin/entrypoint.sh"]