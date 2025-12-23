ARG PHP_VERSION=7.2
ARG SEL_DISTRO=buster
FROM php:${PHP_VERSION}-fpm-${SEL_DISTRO}

ARG PHP_VERSION
ARG SEL_DISTRO

RUN if [ ${PHP_VERSION} = 7.2 ] && [ ${SEL_DISTRO} = buster ]; then \
    sed -i 's|http://deb\.debian\.org/debian|https://archive\.debian\.org/debian|g' /etc/apt/sources.list && \
    sed -i 's|http://security\.debian\.org/debian-security|https://archive\.debian\.org/debian-security|g' /etc/apt/sources.list; \
    fi

RUN apt-get -qq update \
    && apt-get -qq -y --no-install-recommends install \
        procps \
        rsyslog \
        curl \
        unzip \
        wget \
 && rm -rf /var/lib/apt/lists/*

RUN MODULES="mysqli pcntl pdo_mysql"; \
    case "${PHP_VERSION}" in \
        8.5*) ;; \
        *) MODULES="$MODULES opcache" ;; \
    esac; \
    docker-php-ext-install $MODULES

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app/agent/native/ext

ENV REPORT_EXIT_STATUS=1
ENV TEST_PHP_DETAILED=1
ENV NO_INTERACTION=1
ENV TEST_PHP_JUNIT=/app/build/junit.xml

# Disable agent for auxiliary PHP processes to reduce noise in logs
ENV ELASTIC_APM_ENABLED=false

# Create a link to extensions directory to make it easier accessible (paths are different between php releases)
RUN ln -s `find /usr/local/lib/php/extensions/ -name opcache.so | head -n1 | xargs dirname` /tmp/extensions
