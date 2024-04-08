FROM ghcr.io/elastic/apm-agent-php:latest
COPY create-package.sh /bin
WORKDIR /app
ENTRYPOINT ["/bin/create-package.sh"]
