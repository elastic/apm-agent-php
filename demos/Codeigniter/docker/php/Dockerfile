FROM php:7.4-fpm

COPY . /var/www

WORKDIR /var/www

RUN apt-get update && \
    apt-get install -y \
    git \
    zip \
    curl
RUN apt-get install -y zlib1g-dev libicu-dev g++
RUN docker-php-ext-configure intl
RUN docker-php-ext-install intl
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install mysqli
#RUN apt-get install -y php-intl
RUN curl -fsSL https://github.com/elastic/apm-agent-php/releases/download/v1.5/apm-agent-php_1.5_all.deb > /tmp/apm-gent-php.deb \
    && dpkg -i /tmp/apm-gent-php.deb

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN composer create-project codeigniter4/appstarter

RUN echo $(ls)
RUN rm -r appstarter/public/
RUN mv appstarter/* ./
RUN echo $(ls)
RUN chmod -R 777 writable

RUN mv env .env
RUN rm -r app/
RUN mv app-root/ app/
RUN composer install

RUN apt-get clean && rm -rf /var/lib/apt/lists/*
ADD . /var/www/

RUN mkdir -p /usr/bin
RUN mv /var/www/docker-entrypoint.sh /usr/bin/
RUN chmod a+x /usr/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/bin/docker-entrypoint.sh"]