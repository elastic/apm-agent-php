ARG PHP_VERSION=7.2
FROM php:${PHP_VERSION}-cli

RUN apt-get update && apt-get install -y libcurl4 libcurl4-openssl-dev gdb

RUN docker-php-source extract

RUN docker-php-ext-install pdo pdo_mysql opcache curl 

RUN php -m

COPY scripts /scripts

CMD ["/scripts/run.sh"]
