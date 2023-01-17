ARG PHP_VERSION=7.2
FROM php:${PHP_VERSION}-fpm

RUN apt-get -qq update \
    && apt-get -qq -y --no-install-recommends install \
        autoconf \
        build-essential \
        curl \
        libcmocka-dev \
        libcurl4-openssl-dev \
        libsqlite3-dev \
        procps \
        rsyslog \
        unzip \
        wget \
 && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
    mysqli \
    pcntl \
    pdo_mysql

COPY --from=composer:2.3.5 /usr/bin/composer /usr/bin/composer

RUN wget -q https://github.com/Kitware/CMake/releases/download/v3.20.5/cmake-3.20.5-Linux-x86_64.tar.gz -O /tmp/cmake.tar.gz \
      && mkdir /usr/bin/cmake \
      && tar -xpf /tmp/cmake.tar.gz --strip-components=1 -C /usr/bin/cmake \
      && rm /tmp/cmake.tar.gz

ENV PATH="/usr/bin/cmake/bin:${PATH}"

WORKDIR /app/src/ext

ENV REPORT_EXIT_STATUS=1
ENV TEST_PHP_DETAILED=1
ENV NO_INTERACTION=1
ENV TEST_PHP_JUNIT=/app/build/junit.xml
ENV CMOCKA_MESSAGE_OUTPUT=XML
ENV CMOCKA_XML_FILE=/app/build/${PHP_VERSION}-%g-unit-tests-junit.xml

# C call stack capture should be supported on non-Alpine by default
ENV ELASTIC_APM_ASSUME_CAN_CAPTURE_C_STACK_TRACE=true

# Disable agent for auxiliary PHP processes to reduce noise in logs
ENV ELASTIC_APM_ENABLED=false

CMD phpize \
    && CFLAGS="-std=gnu99" ./configure --enable-elastic_apm \
    && make clean \
    && make
