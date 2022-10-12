FROM ruby:2.7.1-alpine3.12

ENV FPM_VERSION 1.11.0
RUN apk add --no-cache \
    alpine-sdk make cpio curl libarchive-tools make php-pear \
    python3 py3-virtualenv py3-setuptools py3-pip \
    rpm unzip xz git tar dpkg \
  && ln -sf python3 /usr/bin/python \
  && gem install --no-document fpm -v ${FPM_VERSION}

## Fix fpm issue, see https://github.com/jordansissel/fpm/issues/1227
ADD fpm_apm.patch /tmp
RUN (cd /usr/local/bundle/gems/fpm-${FPM_VERSION}/ ; patch -p 1 < /tmp/fpm_apm.patch ) \
    && rm -f /tmp/fpm_apk.patch

COPY create-package.sh /bin
WORKDIR /app
ENTRYPOINT ["/bin/create-package.sh"]