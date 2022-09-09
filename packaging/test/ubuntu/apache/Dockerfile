FROM ubuntu:20.04
ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get -qq update \
    && apt-get -qq install -y dpkg-sig gnupg gnupg2 git procps zlib1g-dev libzip-dev wget unzip rsyslog --no-install-recommends

COPY --from=composer:2.3.5 /usr/bin/composer /usr/bin/composer

COPY entrypoint.sh /bin

## Install the specific PHP version in addition with apache2
ARG PHP_VERSION=7.2
ENV PHP_VERSION=$PHP_VERSION
RUN apt-get -qq install -y software-properties-common --no-install-recommends \
    && add-apt-repository ppa:ondrej/php \
    && apt-get -qq update \
    && apt-get -y install \
        apache2 \
        libapache2-mod-php \
    && rm -rf /var/lib/apt/lists/*

RUN echo "php -v: $(php -v)"
RUN echo "php -m: $(php -m)"

## Enable the new installed PHP version.
RUN update-alternatives --set php /usr/bin/php${PHP_VERSION} \
    && a2enmod php${PHP_VERSION}

RUN apt-get -qq install -y software-properties-common --no-install-recommends \
    && add-apt-repository ppa:ondrej/php \
    && apt-get -qq update \
    && apt-get -y install \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-sqlite \
        php${PHP_VERSION}-xml \
        libapache2-mod-php \
    && rm -rf /var/lib/apt/lists/*

RUN echo "php -v: $(php -v)"
RUN echo "php -m: $(php -m)"

ENV TYPE=deb
ENV VERSION=
ENV GITHUB_RELEASES_URL=
WORKDIR /src

ENTRYPOINT ["/bin/entrypoint.sh"]
