FROM php:8.1.8-fpm-alpine

ENV TZ=Europe/Berlin \
    APP_ENV=prod \
    MAILER_URL=null://localhost \
    SHOPWARE_ES_HOSTS= \
    SHOPWARE_ES_ENABLED=0 \
    SHOPWARE_ES_INDEXING_ENABLED=0 \
    SHOPWARE_ES_INDEX_PREFIX= \
    COMPOSER_HOME=/tmp/composer \
    SHOPWARE_HTTP_CACHE_ENABLED=1 \
    SHOPWARE_HTTP_DEFAULT_TTL=7200 \
    SHOPWARE_AUTOMATICALLY_EMPTY_CACHE_ENABLED=false \
    SHOPWARE_EMPTY_CACHE_INTERVAL=86400 \
    BLUE_GREEN_DEPLOYMENT=1 \
    INSTALL_LOCALE=en-GB \
    INSTALL_CURRENCY=EUR \
    INSTALL_ADMIN_USERNAME=admin \
    INSTALL_ADMIN_PASSWORD=shopware \
    CACHE_ADAPTER=default \
    REDIS_CACHE_HOST=redis \
    REDIS_CACHE_PORT=6379 \
    REDIS_CACHE_DATABASE=0 \
    SESSION_ADAPTER=default \
    REDIS_SESSION_HOST=redis \
    REDIS_SESSION_PORT=6379 \
    REDIS_SESSION_DATABASE=1 \
    FPM_PM=dynamic \
    FPM_PM_MAX_CHILDREN=5 \
    FPM_PM_START_SERVERS=2 \
    FPM_PM_MIN_SPARE_SERVERS=1 \
    FPM_PM_MAX_SPARE_SERVERS=3 \
    PHP_MAX_UPLOAD_SIZE=128m \
    PHP_MAX_EXECUTION_TIME=300 \
    PHP_MEMORY_LIMIT=512m \
    LD_PRELOAD="/usr/lib/preloadable_libiconv.so php"

COPY --from=ghcr.io/shyim/supervisord /usr/local/bin/supervisord /usr/bin/supervisord
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/
COPY --from=ghcr.io/shyim/gnu-libiconv:v3.14 /gnu-libiconv-1.15-r3.apk /gnu-libiconv-1.15-r3.apk

RUN apk add --no-cache \
      nginx \
      shadow \
      unzip \
      wget \
      sudo \
      bash \
      patch \
      jq && \
    apk add --no-cache --allow-untrusted /gnu-libiconv-1.15-r3.apk && rm /gnu-libiconv-1.15-r3.apk && \
    install-php-extensions bcmath gd intl mysqli pdo_mysql sockets bz2 gmp soap zip ffi redis opcache && \
    ln -s /usr/local/bin/php /usr/bin/php && \
    ln -sf /dev/stdout /var/log/nginx/access.log && \
    ln -sf /dev/stderr /var/log/nginx/error.log && \
    rm -rf /var/lib/nginx/tmp && \
    ln -sf /tmp /var/lib/nginx/tmp && \
    mkdir -p /var/tmp/nginx/ || true && \
    chown -R www-data:www-data /var/lib/nginx /var/tmp/nginx/ && \
    chmod 777 -R /var/tmp/nginx/ && \
    rm -rf /tmp/* && \
    chown -R www-data:www-data /var/www && \
    usermod -u 1000 www-data

COPY rootfs /

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]

HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1:80/admin