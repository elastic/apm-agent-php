FROM php:8.1-fpm

ADD . /var/www
WORKDIR /var/www

RUN apt-get update && apt-get install -y \
      apt-utils \
      libpq-dev \
      libpng-dev \
      libzip-dev \
      zip unzip \
      git && \
      docker-php-ext-install pdo_mysql && \
      docker-php-ext-install bcmath && \
      docker-php-ext-install gd && \
      docker-php-ext-install zip && \
      apt-get clean && \
      rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY ./_docker/app/php.ini /usr/local/etc/php/conf.d/php.ini

# Install composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- \
    --filename=composer \
    --install-dir=/usr/local/bin

RUN composer install
RUN chmod -R 777 storage/
# RUN curl -fsSL https://github.com/elastic/apm-agent-php/releases/download/v1.5/apm-agent-php_1.5_all.deb > /tmp/apm-gent-php.deb \
#    && dpkg -i /tmp/apm-gent-php.deb

#RUN curl -fsSL https://github.com/elastic/apm-agent-php/releases/download/v1.7.0/apm-agent-php_1.7.0_all.deb > /tmp/apm-gent-php.deb \
#    && dpkg -i /tmp/apm-gent-php.deb

#RUN cp /opt/elastic/apm-agent-php/etc/elastic-apm.ini /tmp/elastic-apm.ini

#RUN sed 's|elastic_apm.bootstrap_php_part_file=/opt/elastic/apm-agent-php/src/bootstrap_php_part.php|elastic_apm.bootstrap_php_part_file=/php-agent-myfork/src/bootstrap_php_part.php|' /tmp/elastic-apm.ini > /opt/elastic/apm-agent-php/etc/elastic-apm.ini
