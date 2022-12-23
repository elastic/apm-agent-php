ARG PHP_VERSION
ARG YII2_PHP_TAG_SUFFIX
FROM yiisoftware/yii2-php:${PHP_VERSION}-${YII2_PHP_TAG_SUFFIX}
RUN echo "PHP_VERSION: ${PHP_VERSION}"
RUN echo "YII2_PHP_TAG_SUFFIX: ${YII2_PHP_TAG_SUFFIX}"

ADD ./Yii2 /app
WORKDIR /app

RUN curl -s https://getcomposer.org/installer | php
RUN composer install

ARG USE_VALGRIND
ENV USE_VALGRIND=${USE_VALGRIND}
RUN echo "USE_VALGRIND: ${USE_VALGRIND}"
COPY ./shared/php-fpm_under_valgrind.sh /app/php-fpm_under_valgrind.sh
RUN chmod +x /app/php-fpm_under_valgrind.sh
COPY ./shared/install_valgrind.sh /app/install_valgrind.sh
RUN chmod +x /app/install_valgrind.sh
RUN /app/install_valgrind.sh "${USE_VALGRIND}"

ARG PHP_AGENT_INSTALL_LOCAL_EXTENSION_BINARY
ENV PHP_AGENT_INSTALL_LOCAL_EXTENSION_BINARY=${PHP_AGENT_INSTALL_LOCAL_EXTENSION_BINARY}
RUN echo "PHP_AGENT_INSTALL_LOCAL_EXTENSION_BINARY: ${PHP_AGENT_INSTALL_LOCAL_EXTENSION_BINARY}"
ARG PHP_AGENT_INSTALL_PACKAGE_URL
ENV PHP_AGENT_INSTALL_PACKAGE_URL=${PHP_AGENT_INSTALL_PACKAGE_URL}
RUN echo "PHP_AGENT_INSTALL_PACKAGE_URL: ${PHP_AGENT_INSTALL_PACKAGE_URL}"
ARG PHP_AGENT_INSTALL_RELEASE_VERSION
ENV PHP_AGENT_INSTALL_RELEASE_VERSION=${PHP_AGENT_INSTALL_RELEASE_VERSION}
RUN echo "PHP_AGENT_INSTALL_RELEASE_VERSION: ${PHP_AGENT_INSTALL_RELEASE_VERSION}"
COPY ./shared/local_agent_code_php.ini /app/local_agent_code_php.ini
COPY ./shared/install_agent.sh /app/install_agent.sh
RUN chmod +x /app/install_agent.sh
RUN /app/install_agent.sh

RUN mv /usr/local/bin/docker-php-entrypoint /app/parent_container_docker_entrypoint.sh
COPY ./Yii2/docker_entrypoint.sh /usr/local/bin/docker-php-entrypoint
RUN chmod +x /usr/local/bin/docker-php-entrypoint
