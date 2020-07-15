ARG PHP_VERSION=7.4
FROM wordpress:php${PHP_VERSION}-fpm
WORKDIR /src
RUN echo 'extension=elastic_apm.so' > /usr/local/etc/php/php.ini
RUN echo 'elastic_apm.bootstrap_php_part_file=/usr/share/php/apm-agent-php/bootstrap_php_part.php' >> /usr/local/etc/php/php.ini
