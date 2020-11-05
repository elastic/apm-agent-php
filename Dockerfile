ARG PHP_VERSION=7.2
FROM php:${PHP_VERSION}-fpm

RUN apt-get -qq update \
 && apt-get -qq install -y \
    autoconf \
    build-essential \
    curl \
    libcmocka-dev \
    libcurl4-openssl-dev \
    procps \
    rsyslog \
    unzip \
    wget \
    --no-install-recommends \
 && rm -rf /var/lib/apt/lists/*

RUN php -r "copy('https://raw.githubusercontent.com/composer/getcomposer.org/baecae060ee7602a9908f2259f7460b737839972/web/installer', 'composer-setup.php');" \
 && php -r "if (hash_file('sha384', 'composer-setup.php') === '572cb359b56ad9ae52f9c23d29d4b19a040af10d6635642e646a7caa7b96de717ce683bd797a92ce99e5929cc51e7d5f') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
 && php composer-setup.php --install-dir=/usr/bin --filename=composer --version=1.10.10 \
 && php -r "unlink('composer-setup.php');"

RUN wget -q https://github.com/Kitware/CMake/releases/download/v3.18.4/cmake-3.18.4-Linux-x86_64.tar.gz -O /tmp/cmake.tar.gz \
      && mkdir /usr/bin/cmake \
      && tar -xpf /tmp/cmake.tar.gz --strip-components=1 -C /usr/bin/cmake \
      && rm /tmp/cmake.tar.gz

ENV PATH="/usr/bin/cmake/bin:${PATH}"

WORKDIR /app/src/ext

ENV REPORT_EXIT_STATUS=1
ENV TEST_PHP_DETAILED=1
ENV NO_INTERACTION=1
ENV TEST_PHP_JUNIT=/app/junit.xml
ENV CMOCKA_MESSAGE_OUTPUT=XML
ENV CMOCKA_XML_FILE=/app/${PHP_VERSION}-unit-tests-junit.xml

CMD phpize \
    && CFLAGS="-std=gnu99" ./configure --enable-elastic_apm \
    && make clean \
    && make
