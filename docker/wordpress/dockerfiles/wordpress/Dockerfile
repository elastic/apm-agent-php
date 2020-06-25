FROM wordpress:php7.3-fpm-alpine

COPY ./data/wordpress/php/. /var/www/html/.

# Compile the ext
RUN apk add build-base autoconf curl-dev
COPY ./data/ext /var/elastic_apm
COPY ./data/elastic_apm.ini /usr/local/etc/php/conf.d/elastic_apm.ini
COPY ./data/wordpress/install-ext.sh /var/elastic_apm/install-ext.sh
RUN touch /var/elastic_apm.log
RUN chown www-data:www-data /var/elastic_apm.log
RUN chmod +x /var/elastic_apm/install-ext.sh
RUN /var/elastic_apm/install-ext.sh
