FROM yiisoftware/yii2-php:8.0-apache

ADD . /app
WORKDIR /app

RUN curl -s https://getcomposer.org/installer | php

RUN composer update
RUN composer install

RUN curl -fsSL https://github.com/elastic/apm-agent-php/releases/download/v1.5/apm-agent-php_1.5_all.deb > /tmp/apm-gent-php.deb \
    && dpkg -i /tmp/apm-gent-php.deb

RUN mkdir -p /usr/bin
RUN mv /app/docker-entrypoint.sh /usr/bin/
RUN chmod a+x /usr/bin/docker-entrypoint.sh

#ENTRYPOINT ["/usr/bin/docker-entrypoint.sh"]
#EXPOSE 80/tcp
