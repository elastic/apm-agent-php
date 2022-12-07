FROM webdevops/php-nginx:8.0

ADD . /application
WORKDIR /application

RUN curl -s https://getcomposer.org/installer | php
RUN composer create-project --prefer-dist cakephp/app:4.* cms

RUN mv cms/* ./

RUN composer install

RUN mv article_app/templates/Articles/ templates/Articles/
RUN rm -r templates/Pages/
RUN mv article_app/templates/Pages/ templates/Pages/
RUN mv article_app/src/Controller/ArticlesController.php src/Controller/ArticlesController.php
RUN mv article_app/src/Model/Table/ArticlesTable.php src/Model/Table/ArticlesTable.php


RUN curl -fsSL https://github.com/elastic/apm-agent-php/releases/download/v1.5/apm-agent-php_1.5_all.deb > /tmp/apm-gent-php.deb \
    && dpkg -i /tmp/apm-gent-php.deb
