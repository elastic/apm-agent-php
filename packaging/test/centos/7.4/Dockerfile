FROM centos:centos7

RUN yum install -y epel-release yum-utils \
    && rpm -Uvh http://rpms.remirepo.net/enterprise/remi-release-7.rpm \
    && yum update -y \
    && yum-config-manager --enable remi-php74 \
    && yum install -y php php-mbstring php-mysql php-xml

RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/bin/composer

COPY entrypoint.sh /bin
WORKDIR /src

ENTRYPOINT ["/bin/entrypoint.sh"]