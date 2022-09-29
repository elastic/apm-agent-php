FROM nginx:alpine

COPY nginx.conf  /etc/nginx/
COPY templates /etc/nginx/templates/
RUN echo "upstream php-upstream { server php:9000; }" > /etc/nginx/conf.d/upstream.conf

RUN apk add --no-cache git
RUN git clone https://github.com/symfony/demo.git
ADD . /var/www/symfony/
RUN cp -R /demo/public/ /var/www/symfony

EXPOSE 80
EXPOSE 443
