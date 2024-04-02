FROM docker.elastic.co/observability-ci/fpm:latest

COPY create-package.sh /bin
WORKDIR /app
ENTRYPOINT ["/bin/create-package.sh"]
