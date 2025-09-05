---
mapped_pages:
  - https://www.elastic.co/guide/en/apm/agent/php/current/setup.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/_limitations.html
applies_to:
  stack:
  serverless:
    observability:
  product:
    apm_agent_php: ga
---

# Set up the APM PHP Agent [setup]


## Prerequisites [setup-prerequisites]


### Operating system and architecture [_operating_system_and_architecture]

We officially support Linux systems (glibc, deb and rpm packages) and Alpine Linux (musl libc - apk packages) for x86_64 (AMD64) processors.

::::{note}
Experimentally, we also provide packages for the ARM64 architecture - please note that these packages have not been fully tested.
::::



### PHP [_php]

The agent supports PHP versions 7.2-8.4.


### curl [_curl]

The agent requires `libcurl` 7.58 or later.


## Installation [setup-installation]

Install the agent using one of the [packages for supported platforms](https://github.com/elastic/apm-agent-php/releases/latest).


### Using RPM package (RHEL/CentOS, Fedora) [setup-rpm]

```bash
rpm -ivh <package-file>.rpm
```


### Using DEB package (Debian, Ubuntu 18+) [setup-deb]

```bash
dpkg -i <package-file>.deb
```


### Using APK package (Alpine) [setup-apk]

```bash
apk add --allow-untrusted <package-file>.apk
```


### Build from source [build-from-source]

If you can’t find your distribution, you can install the agent by building it from the source. The following instructions will build the APM agent using the same docker environment that Elastic uses to build our official packages.

::::{note}
The agent is currently only available for Linux operating system.
::::


1. Download the agent source from [https://github.com/elastic/apm-agent-php/](https://github.com/elastic/apm-agent-php/).
2. Execute the following commands to build the agent and install it:

```bash
cd apm-agent-php
# for linux glibc - libc distributions (Ubuntu, Redhat, etc)
export BUILD_ARCHITECTURE=linux-x86-64
# for linux with musl - libc distributions (Alpine)
export BUILD_ARCHITECTURE=linuxmusl-x86-64
# provide a path to php-config tool
export PHP_CONFIG=php-config

# build extensions
make -f .ci/Makefile build

# run extension tests
PHP_VERSION=`$PHP_CONFIG --version | cut -d'.' -f 1,2` make -f .ci/Makefile run-phpt-tests

# install agent extensions
sudo cp agent/native/_build/${BUILD_ARCHITECTURE}-release/ext/elastic_apm-*.so `$PHP_CONFIG --extension-dir`

# install automatic loader
sudo cp agent/native/_build/${BUILD_ARCHITECTURE}-release/loader/code/elastic_apm_loader.so `$PHP_CONFIG --extension-dir`
```

Enable the extension by adding the following to your `php.ini` file:

```ini
extension=elastic_apm_loader.so
elastic_apm.bootstrap_php_part_file=<repo root>/agent/php/bootstrap_php_part.php
```

To work, the agent needs both the built `elastic_apm-*.so` and the downloaded source files. So if you would like to build `elastic_apm-*.so` on one machine and then deploy it on a different machine, you will need to copy both the built `elastic_apm-*.so` and the downloaded source files.


## Limitations [limitations]


### `open_basedir` PHP configuration option [limitation-open_basedir]

Please be aware that if the [`open_basedir`](https://www.php.net/manual/en/ini.core.php#ini.open-basedir) option is configured in your php.ini, the installation directory of the agent (by default `/opt/elastic/apm-agent-php`) must be located within a path included in the [`open_basedir`](https://www.php.net/manual/en/ini.core.php#ini.open-basedir) value. Otherwise, the agent will not be loaded correctly.


### `Xdebug` stability and memory issues [limitation-xdebug]

We strongly advise against running the agent alongside the xdebug extension. Using both extensions simultaneously can lead to stability issues in the instrumented application, such as memory leaks. It is highly recommended to disable xdebug, preferably by preventing it from loading in the `php.ini` configuration file.

## Limitations

Please be aware that if the [open_basedir](https://www.php.net/manual/en/ini.core.php#ini.open-basedir) option is configured in your php.ini, the installation directory of the agent (by default /opt/elastic/apm-agent-php) must be located within a path included in the [open_basedir](https://www.php.net/manual/en/ini.core.php#ini.open-basedir) configuration. Otherwise, the agent will not be loaded correctly.