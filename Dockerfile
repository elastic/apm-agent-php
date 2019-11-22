FROM wordpress:php7.3-fpm-alpine

RUN apk --no-cache add build-base autoconf curl-dev

WORKDIR /app/src/ext

CMD phpize \
    && ./configure --enable-elasticapm \
    && make clean \
    && make \
    && make install
