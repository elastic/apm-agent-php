FROM centos:centos7
ARG PHP_VERSION=7.2
ENV PHP_VERSION ${PHP_VERSION}
## Package versioning for the PHP does not use .
RUN export PHP_VERSION_TRANSFORMED=$(echo "${PHP_VERSION}" | sed 's#\.##g') \
    && yum install -y epel-release yum-utils \
    && rpm -Uvh http://rpms.remirepo.net/enterprise/remi-release-7.rpm \
    && yum update -y \
    && yum-config-manager --enable remi-php${PHP_VERSION_TRANSFORMED} \
    && yum install -y php php-mbstring php-mysql php-xml rsyslog

## sh: git: command not found
# the zip extension and unzip command are both missing, skipping.
RUN yum update -y \
    && yum install -y git gnupg2 perl-Digest-SHA unzip wget \
    && yum clean all

COPY --from=composer:2.3.5 /usr/bin/composer /usr/bin/composer

# To support tar and rpm packages
ENV TYPE=rpm
ENV VERSION=
ENV GITHUB_RELEASES_URL=
COPY entrypoint.sh /bin
WORKDIR /src

ENTRYPOINT ["/bin/entrypoint.sh"]
