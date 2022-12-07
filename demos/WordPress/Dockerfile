ARG WORDPRESS_DOCKER_LABEL
FROM wordpress:${WORDPRESS_DOCKER_LABEL}
RUN echo "WORDPRESS_DOCKER_LABEL: ${WORDPRESS_DOCKER_LABEL}"

RUN apt-get -qq update \
    && apt-get -qq -y --no-install-recommends install \
        logrotate \
        procps \
        rsyslog \
    && rm -rf /var/lib/apt/lists/*

ARG PHP_AGENT_INSTALL_LOCAL_EXTENSION_BINARY
ENV PHP_AGENT_INSTALL_LOCAL_EXTENSION_BINARY=${PHP_AGENT_INSTALL_LOCAL_EXTENSION_BINARY}
RUN echo "PHP_AGENT_INSTALL_LOCAL_EXTENSION_BINARY: ${PHP_AGENT_INSTALL_LOCAL_EXTENSION_BINARY}"
ARG PHP_AGENT_INSTALL_PACKAGE_FROM_URL
ENV PHP_AGENT_INSTALL_PACKAGE_FROM_URL=${PHP_AGENT_INSTALL_PACKAGE_FROM_URL}
RUN echo "PHP_AGENT_INSTALL_PACKAGE_FROM_URL: ${PHP_AGENT_INSTALL_PACKAGE_FROM_URL}"
ARG PHP_AGENT_INSTALL_RELEASE_VERSION
ENV PHP_AGENT_INSTALL_RELEASE_VERSION=${PHP_AGENT_INSTALL_RELEASE_VERSION}
RUN echo "PHP_AGENT_INSTALL_RELEASE_VERSION: ${PHP_AGENT_INSTALL_RELEASE_VERSION}"
RUN mkdir -p /app
COPY local_agent_code_php.ini /app/local_agent_code_php.ini
COPY install_agent.sh /app/install_agent.sh
RUN chmod a+x /app/install_agent.sh
RUN /app/install_agent.sh

RUN mkdir -p /app
RUN mv /usr/local/bin/docker-entrypoint.sh /app/parent_container_docker_entrypoint.sh
COPY docker_entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
