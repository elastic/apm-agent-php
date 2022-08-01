FROM shyim/shopware:6.2.0

RUN curl -fsSL https://github.com/elastic/apm-agent-php/releases/download/v1.5.2/apm-agent-php_1.5.2_all.apk > /tmp/apm-agent-php.apk \
    && apk add --allow-untrusted /tmp/apm-agent-php.apk

