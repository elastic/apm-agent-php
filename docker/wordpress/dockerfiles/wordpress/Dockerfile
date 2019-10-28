FROM wordpress:php7.3-fpm-alpine

COPY ./data/wordpress/php/. /var/www/html/.

# Compile the ext
RUN apk add build-base autoconf curl-dev
COPY ./data/ext /var/elasticapm
COPY ./data/elasticapm.ini /usr/local/etc/php/conf.d/elasticapm.ini
COPY ./data/wordpress/install-ext.sh /var/elasticapm/install-ext.sh
RUN touch /var/elasticapm.log
RUN chown www-data:www-data /var/elasticapm.log
RUN chmod +x /var/elasticapm/install-ext.sh
RUN /var/elasticapm/install-ext.sh
