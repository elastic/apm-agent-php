FROM fedora:41
ARG PHP_VERSION=8.4
ENV PHP_VERSION ${PHP_VERSION}

RUN echo "PHP_VERSION: $PHP_VERSION"
RUN echo "ls -R -l" && echo `ls -R -l`
RUN ls -1 /etc/*release | xargs -i sh -c 'echo {} && cat {}'

RUN dnf -y update && dnf install -y \
    git \
    gnupg2 \
    logrotate \
    lsof \
    perl-Digest-SHA \
    rsyslog \
    unzip \
    wget \
    which \
    procps

    #    && yum-config-manager --enable remi-php${PHP_VERSION_TRANSFORMED} \
RUN export PHP_VERSION_TRANSFORMED=$(echo "${PHP_VERSION}" | sed 's#\.##g')  \
    FEDORA_VERSION_ID=$(cat /etc/*release | grep VERSION_ID | cut -d '=' -f 2) && echo "FEDORA_VERSION_ID: $FEDORA_VERSION_ID" \
    && dnf -y install https://rpms.remirepo.net/fedora/remi-release-$FEDORA_VERSION_ID.rpm
RUN dnf -y install dnf-plugins-core

RUN dnf -y module reset php \
    && dnf -y module enable php:remi-$PHP_VERSION -y

#    composer \
RUN dnf -y install \
    php \
    php-cli \
    php-curl \
    php-json \
    php-mbstring \
    php-mysqli \
    php-pdo_sqlite \
    php-pcntl \
    php-posix \
    php-xml


COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Disable agent for auxiliary PHP processes to reduce noise in logs
ENV ELASTIC_APM_ENABLED=false

# It seems that even though we install rsyslog and start it (search for ensureSyslogIsRunning in .ci/validate_agent_installation.sh)
# for some reason on CentOS rsyslog does not receive messages from the agent
# so in order to work around this issue we escalate all log levels on CentOS
ENV ELASTIC_APM_PHP_TESTS_ESCALATED_RERUNS_PROD_CODE_LOG_LEVEL_OPTION_NAME=log_level

# To support tar and rpm packages
ENV TYPE=rpm
ENV VERSION=
ENV GITHUB_RELEASES_URL=
COPY entrypoint.sh /bin
WORKDIR /app

ENTRYPOINT ["/bin/entrypoint.sh"]
