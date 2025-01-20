ARG IMAGE_TAG=latest
FROM ghcr.io/elastic/apm-agent-php:${IMAGE_TAG}
COPY create-package.sh /bin
WORKDIR /app
ENTRYPOINT ["/bin/create-package.sh"]
