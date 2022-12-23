FROM shyim/shopware:6.2.0

RUN mkdir -p /app

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
