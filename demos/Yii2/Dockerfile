FROM yiisoftware/yii2-php:8.0-apache

ADD . /app
WORKDIR /app

RUN curl -s https://getcomposer.org/installer | php

RUN composer update
RUN composer install

RUN curl -fsSL https://github.com/elastic/apm-agent-php/releases/download/v1.6.2/apm-agent-php_1.6.2_all.deb > /tmp/apm-gent-php.deb \
    && dpkg -i /tmp/apm-gent-php.deb

RUN mv /usr/local/bin/docker-php-entrypoint /app/parent_container_docker_entrypoint.sh
COPY docker-entrypoint.sh /usr/local/bin/docker-php-entrypoint
RUN chmod +x /usr/local/bin/docker-php-entrypoint
