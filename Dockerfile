FROM wordpress:php7.4-fpm

RUN apt-get -qq update \
 && apt-get -qq install -y \
    build-essential \
    autoconf \
    curl \
    libcurl4-openssl-dev \
	--no-install-recommends \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app/src/ext

ENV REPORT_EXIT_STATUS=1
ENV TEST_PHP_DETAILED=1
ENV NO_INTERACTION=1
ENV TEST_PHP_JUNIT=/app/junit.xml

CMD phpize \
    && ./configure --enable-elasticapm \
    && make clean \
    && make
