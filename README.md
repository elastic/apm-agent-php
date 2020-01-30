# PHP agent for Elastic APM Server

The official PHP agent for [Elastic APM](https://www.elastic.co/products/apm).
This agent is a PHP extension that must be installed in your PHP environment.

## Usage

You need to compile and install this library as PHP extension.
At the moment, this extension is only available for Unix like OS.
To install the extension you need to execute the command as follows:

```bash
cd src/ext
phpize
./configure --enable-elasticapm
make clean
make
sudo make install
```

You need to enable the extension in your `php.ini`. You need to add the following
line to `php.ini`:

```
extension=elasticapm.so
```

### Local development

If you don't want to install any of the dependencies you might need to compile and install the library then you can use the Dockerfile.


```bash
docker build --tag apm-agent-php .

## To compile the library
docker run --rm -ti -v $(pwd):/app apm-agent-php

## To test the Library
docker run --rm -ti -v $(pwd):/app apm-agent-php make test

## To install the library
docker run --rm -ti -v $(pwd):/app apm-agent-php make install
```

## Configure

You can configure the Elastic APM agent using the following ini settings for PHP:

```ini
elasticapm.enable = 0
elasticapm.host = localhost:8200
elasticapm.service_name = "Unknown PHP service"
elasticapm.log = 
elasticapm.log_level= 0
```

By default, the extension is disabled. You need to enable it setting `elasticapm.enable=1`.

You can enable the logging of the PHP agent adding a file path in `elasticapm.log`.
You can also specify the log level using the `elasticapm.log_level` key. The
default value is `0` that means log everything (trace). 

The log levels are:
```
0 trace
1 debug
2 info
3 warning
4 error
5 fatal
```

E.g. if you specify `4`, all log `0`, `1`, `2` and `3` will be ignored.

You can see an example of `elasticapm.ini` [here](src/ext/elasticapm.ini).

If you want you can also change the Elastic APM agent at runtime, using the
following PHP code:

```php
ini_set('elasticapm.enable', '1');
ini_set('elasticapm.host', 'myhost:8200');
ini_set('elasticapm.service_name', 'test');
ini_set('elasticapm.log', '/tmp/elasticapm.log');
ini_set('elasticapm.log_level', '4');
```

## Configure with Elastic Cloud

You can also configure the PHP agent to send data to an [Elastic Cloud](https://www.elastic.co/cloud/)
APM instance. You just need to configure the `elasticapm.host` and `elasticapm.secret_token`.

The `host` and `secret_token` are available in the APM section of Elastic Cloud
(see the image below):

![Elastic Cloud APM configuration](docs/elastic_cloud_apm_config.png)

You can set the host and the secret token using the following PHP code:

```php
ini_set('elasticapm.host', 'insert here the host URL');
ini_set('elasticapm.secret_token', 'insert here you token');
```

or using the `elasticapm.ini` settings:

```ini
elasticapm.host=insert here the host URL
elasticapm.secret_token=insert here you token
```

## Note

**This project is still in development. Please do not use in a production environment!**

## Authors

- [Enrico Zimuel](https://www.zimuel.it)
- [Philip Krauss](https://github.com/philkra)

## Copyright

Copyright 2019 Elasticsearch BV.
Licensed under the [Apache License, Version 2.0](LICENSE).
