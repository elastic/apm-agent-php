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

RUN php -r "copy('https://raw.githubusercontent.com/composer/getcomposer.org/baecae060ee7602a9908f2259f7460b737839972/web/installer', 'composer-setup.php');" \
 && php -r "if (hash_file('sha384', 'composer-setup.php') === '572cb359b56ad9ae52f9c23d29d4b19a040af10d6635642e646a7caa7b96de717ce683bd797a92ce99e5929cc51e7d5f') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
 && php composer-setup.php --install-dir=/usr/bin --filename=composer --version=1.10.10 \
 && php -r "unlink('composer-setup.php');"

# To support tar and rpm packages
ENV TYPE=rpm
ENV VERSION=
ENV GITHUB_RELEASES_URL=
COPY entrypoint.sh /bin
WORKDIR /src

ENTRYPOINT ["/bin/entrypoint.sh"]
