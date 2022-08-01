#!/usr/bin/env bash

if [[ $CACHE_ADAPTER == "redis" ]]; then
    cp /etc/shopware/configs/redis.yml /var/www/html/config/packages/redis.yml
fi

if [[ $SESSION_ADAPTER == "redis" ]]; then
    echo "session.save_handler = redis" > /usr/local/etc/php/conf.d/redis.ini
    echo "session.save_path = \"tcp://${REDIS_SESSION_HOST}:${REDIS_SESSION_PORT}?database=${REDIS_SESSION_DATABASE}\"" >> /usr/local/etc/php/conf.d/redis.ini
fi