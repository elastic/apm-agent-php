ARG image_version=latest
FROM ghcr.io/elastic/apm-agent-php:${image_version}
COPY create-package.sh /bin
WORKDIR /app
ENTRYPOINT ["/bin/create-package.sh"]
