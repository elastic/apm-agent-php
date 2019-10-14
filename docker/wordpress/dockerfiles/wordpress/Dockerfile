FROM wordpress:php7.3-fpm-alpine

COPY ./data/wordpress/wp-config.php /var/www/html/wp-config.php
RUN echo "<?php phpinfo();" > /var/www/html/info.php

# Compile the ext
RUN apk add build-base autoconf curl-dev
COPY ./data/elasticapm.ini /usr/local/etc/php/conf.d/elasticapm.ini
COPY ./data/ext /var/elasticapm
COPY ./data/wordpress/install-ext.sh /var/elasticapm/install-ext.sh
RUN chmod +x /var/elasticapm/install-ext.sh
RUN /var/elasticapm/install-ext.sh
