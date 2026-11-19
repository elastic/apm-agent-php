ARG PHP_VERSION=7.2
FROM php:${PHP_VERSION}-cli

RUN apt-get update && apt-get install -y libcurl4 libcurl4-openssl-dev gdb

RUN docker-php-source extract

RUN MODULES="pdo pdo_mysql curl"; \
    case "${PHP_VERSION}" in \
        8.5*) ;; \
        *) MODULES="$MODULES opcache" ;; \
    esac; \
    docker-php-ext-install $MODULES 

RUN php -m

COPY scripts /scripts

CMD ["/scripts/run.sh"]
