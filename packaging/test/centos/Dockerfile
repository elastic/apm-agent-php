FROM centos:centos7
ARG PHP_VERSION=7.2
ENV PHP_VERSION ${PHP_VERSION}

COPY CentOS-Base.repo /etc/yum.repos.d/CentOS-Base.repo

RUN yum update -y \
    && yum install -y \
            git \
            gnupg2 \
            logrotate \
            lsof \
            perl-Digest-SHA \
            rsyslog \
            unzip \
            wget \
            which

## Package versioning for the PHP does not use .
RUN export PHP_VERSION_TRANSFORMED=$(echo "${PHP_VERSION}" | sed 's#\.##g') \
    && yum install -y epel-release yum-utils \
    && rpm -Uvh http://rpms.remirepo.net/enterprise/remi-release-7.rpm \
    && yum update -y \
    && yum-config-manager --enable remi-php${PHP_VERSION_TRANSFORMED} \
    && yum install -y \
            php \
            php-mbstring \
            php-mysql \
            php-mysqli \
            php-pcntl \
            php-posix \
            php-sqlite \
            php-xml \
    && yum clean all

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
