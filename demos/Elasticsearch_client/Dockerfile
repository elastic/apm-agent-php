ARG PHP_VERSION
FROM php:${PHP_VERSION}-cli
RUN echo "PHP_VERSION: ${PHP_VERSION}"

RUN apt-get -qq update \
    && apt-get -qq -y --no-install-recommends install \
        apt-utils \
        git \
        libzip-dev \
        logrotate \
        procps \
        rsyslog \
        zip unzip \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN mkdir -p /app
COPY ./Elasticsearch_client /app
RUN rm -f /app/index.php
WORKDIR /app

# Install composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- \
    --filename=composer \
    --install-dir=/usr/local/bin

RUN composer install

CMD ["php", "-S", "0.0.0.0:80"]
