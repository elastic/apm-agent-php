# What is Shopware?

Shopware is a trendsetting ecommerce platform to power your online business. Our ecommerce solution offers the perfect combination of beauty & brains you need to build and customize a fully responsive online store.

![Shopware Logo](https://assets.shopware.com/media/logos/shopware_logo_blue.svg)


# How to use this image

To run Shopware 6 you will need a compatible MySQL or MariaDB container.

Smallest example with docker-compose

```yaml
version: "3.8"
services:
  mysql:
    image: mysql:5.7
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: shopware
      MYSQL_USER: shopware
      MYSQL_PASSWORD: shopware
  shopware:
    image: shyim/shopware:6.2.0
    environment:
      APP_SECRET: 440dec3766de53010c5ccf6231c182acfc90bd25cff82e771245f736fd276518
      INSTANCE_ID: 10612e3916e153dd3447850e944a03fabe89440970295447a30a75b151bd844e
      APP_URL: http://localhost
      DATABASE_HOST: mysql
      DATABASE_URL: mysql://shopware:shopware@mysql:3306/shopware
    ports:
      - 80:80
```

[![Try](https://github.com/play-with-docker/stacks/raw/cff22438cb4195ace27f9b15784bbb497047afa7/assets/images/button.png)](https://labs.play-with-docker.com/?stack=https://raw.githubusercontent.com/shyim/shopware-image/master/docker-compose.yml)

The installation will be accessible at `http://localhost`. The default credentials for the administration are `admin` and `shopware` as password.

Following environment can be set:

| Variable                     | Default Value    | Description                                             |
|------------------------------|------------------|---------------------------------------------------------|
| APP_ENV                      | prod             | Environment                                             |
| APP_SECRET                   | (empty)          | Can be generated with `openssl rand -hex 32`            |
| APP_URL                      | (empty)          | Where Shopware will be accessible                       |
| INSTANCE_ID                  | (empty)          | Unique Identifier for the Store: Can be generated with `openssl rand -hex 32`                        |
| DATABASE_HOST                | (empty)          | Host of MySQL (needed for for checking is MySQL alive)  |
| BLUE_GREEN_DEPLOYMENT        | 1                | This needs super priviledge to create trigger           |
| DATABASE_URL                 | (empty)          | MySQL credentials as DSN                                |
| DATABASE_SSL_CA              | (empty)          | Path to SSL CA file (needs to be readable for uid 1000) |
| DATABASE_SSL_CERT            | (empty)          | Path to SSL Cert file (needs to be readable for uid 1000) |
| DATABASE_SSL_KEY             | (empty)          | Path to SSL Key file (needs to be readable for uid 1000) |
| DATABASE_SSL_DONT_VERIFY_SERVER_CERT | (empty)          | Disables verification of the server certificate (1 disables it) |
| MAILER_URL                   | null://localhost | Mailer DSN (Admin Configuration overwrites this)        |
| SHOPWARE_ES_HOSTS            | (empty)          | Elasticsearch Hosts                                     |
| SHOPWARE_ES_ENABLED          | 0                | Elasticsearch Support Enabled?                          |
| SHOPWARE_ES_INDEXING_ENABLED | 0                | Elasticsearch Indexing Enabled?                         |
| SHOPWARE_ES_INDEX_PREFIX     | (empty)          | Elasticsearch Index Prefix                              |
| COMPOSER_HOME                | /tmp/composer    | Caching for the Plugin Manager                          |
| SHOPWARE_HTTP_CACHE_ENABLED  | 1                | Is HTTP Cache enabled?                                  |
| SHOPWARE_HTTP_DEFAULT_TTL    | 7200             | Default TTL for Http Cache                              |
| SHOPWARE_AUTOMATICALLY_EMPTY_CACHE_ENABLED | false            | Empty cache automatically. See [Caches & Indexes > Empty cache automatically](https://docs.shopware.com/en/shopware-6-en/configuration/caches-indexes#empty-cache-automatically) |
| SHOPWARE_EMPTY_CACHE_INTERVAL| 86400 (24 hours) | Interval with which to clear the cache in seconds.      |
| DISABLE_ADMIN_WORKER         | false            | Disables the admin worker                               |
| INSTALL_LOCALE               | en-GB            | Default locale for the Shop                             |
| INSTALL_CURRENCY             | EUR              | Default currency for the Shop                           |
| INSTALL_ADMIN_USERNAME       | admin            | Default admin username                                  |
| INSTALL_ADMIN_PASSWORD       | shopware         | Default admin password                                  |
| CACHE_ADAPTER                | default          | Set this to redis to enable redis caching               |
| REDIS_CACHE_HOST             | redis            | Host for redis caching                                  |
| REDIS_CACHE_PORT             | 6379             | Redis cache port                                        |
| REDIS_CACHE_DATABASE         | 0                | Redis database index                                    |
| SESSION_ADAPTER              | default          | Set this to redis to enable redis session adapter       |
| REDIS_SESSION_HOST           | redis            | Host for redis session                                  |
| REDIS_SESSION_PORT           | 6379             | Redis session port                                      |
| REDIS_SESSION_DATABASE       | 0                | Redis session index                                     |
| ACTIVE_PLUGINS               | (empty)          | A list of plugins which should be installed and updated |
| TZ                           | Europe/Berlin    | PHP default timezone                                    |
| PHP_MAX_UPLOAD_SIZE          | 128m             | See php documentation                                   |
| PHP_MAX_EXECUTION_TIME       | 300              | See php documentation                                   |
| PHP_MEMORY_LIMIT             | 512m             | See php documentation                                   |
| FPM_PM                       | dynamic          | See php fpm documentation                               |
| FPM_PM_MAX_CHILDREN          | 5                | See php fpm documentation                               |
| FPM_PM_START_SERVERS         | 2                | See php fpm documentation                               |
| FPM_PM_MIN_SPARE_SERVERS     | 1                | See php fpm documentation                               |
| FPM_PM_MAX_SPARE_SERVERS     | 3                | See php fpm documentation                               |

When Shopware with SSL behind a reverse proxy such as NGINX which is responsible for doing TLS termination, be sure configure [Trusted Headers](https://symfony.com/doc/current/deployment/proxies.html).

# Updates

When you update the image version, automatically all required migrations will run. Downgrade works in a similar way. Please check before here the Blue-Green compatibility of Shopware.

# Running multiple containers

See `docker-compose-advanced.yml` for a full docker-compose example.

## Mode: default

* The container will check is Shopware installed and install or update it (and execute hooks). 
* Will start Web server

```yaml
command: ['default']
```

## Mode: web

* Will start Web server

```yaml
command: ['web']
```

## Mode: maintenance

* The container will check is Shopware installed and install or update it (and execute hooks). 

```yaml
command: ['maintenance']
```

## Mode: cli

* Allows to run cli commands like message consumer and other tasks

```yaml
command: ['cli', 'symfony:command', 'arg1', 'arg2']
```

# Volumes

| Path                           | Description                                     |
|--------------------------------|-------------------------------------------------|
| /state                         | Contains state about current installed version. |
| /var/www/html/custom/plugins   | Installed plugins                               |
| /var/www/html/files            | Documents and other private files               |
| /var/www/html/var/log          | Logs                                            |
| /var/www/html/public/theme     | Compiled theme files                            |
| /var/www/html/public/media     | Uploaded files                                  |
| /var/www/html/public/bundles   | Bundle Assets                                   |
| /var/www/html/public/sitemap   | Sitemap                                         |
| /var/www/html/public/thumbnail | Generated Thumbnails                            |
| /var/www/html/config/jwt       | JWT Certificate for API                         |


## Reducing usage of Volumes

* /state
  * The state can be ignored by having a `INSTALLED_SHOPWARE_VERSION` environment variable with the current used Shopware version. This will be used to detect if database migrations needs to be executed.
* /config/jwt
  * In Kubernetes you can mount a secretmap to this location with your certificates
* /public/*
  * Use external storage adapter, see Shopware documentation

# Extending the image

## Additional hooks

* To run script on installation, add a new file to `/etc/shopware/scripts/on-install/xx.sh`
* To run script on update, add a new file to `/etc/shopware/scripts/on-update/xx.sh`
* To run script on startup, add a new file to `/etc/shopware/scripts/on-startup/xx.sh`

## Install plugins from packages.shopware.com

```docker
FROM shyim/shopware:6.4

# Add repository
RUN jq '.repositories += [{"type": "composer","url": "https://packages.shopware.com/","options": {"http": {"header": ["Token: MyToken"]}}}]' /var/www/html/composer.json > /var/www/html/composer2.json && \
  cp composer2.json composer.json && \
  chown 1000:1000 composer.json

RUN sudo -E -u www-data composer require store.shopware.com/swagcmsextensions
```
