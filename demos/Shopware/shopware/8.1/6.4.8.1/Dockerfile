FROM ghcr.io/shyim/shopware-php:8.1.8

COPY patches /usr/local/src/sw-patches

RUN cd /var/www/html && \
    wget -qq "https://releases.shopware.com/sw6/install_6.4.8.1_0d8536adc1256503651ed5a60aef9c10ddfd03b0.zip" && \
    unzip -qq *.zip && \
    rm *.zip && \
    mkdir /state && \
    touch /var/www/html/install.lock && \
    echo "6.4.8.1" > /shopware_version && \
    for f in /usr/local/src/sw-patches/*.patch; do patch -p1 < $f || true; done && \
    chown -R www-data:www-data /var/www

VOLUME /state /var/www/html/custom/plugins /var/www/html/files /var/www/html/var/log /var/www/html/public/theme /var/www/html/public/media /var/www/html/public/bundles /var/www/html/public/sitemap /var/www/html/public/thumbnail /var/www/html/config/jwt