---
mapped_pages:
  - https://www.elastic.co/guide/en/apm/agent/php/current/configuration.html
---

# Configuration [configuration]

Utilize configuration options to adapt the Elastic APM agent to your needs. One way to configure settings is with the `php.ini` file:

```ini
elastic_apm.server_url=http://localhost:8200
elastic_apm.service_name="My service"
```

The agent can also be configured using environment variables:

```shell
export ELASTIC_APM_SERVER_URL="http://localhost:8200"
export ELASTIC_APM_SERVICE_NAME="My service"
```

::::{note}
If you use environment variables to configure the agent, make sure the process running your PHP code inherits those environment variables after they were set.
::::



## {{ess}} on {{ecloud}} [configure-ess]

The agent can be configured to send data to an [{{ess}} APM instance](https://www.elastic.co/cloud/elasticsearch-service?page=docs&placement=docs-body) by setting the [`server_url`](/reference/configuration-reference.md#config-server-url) and [`secret_token`](/reference/configuration-reference.md#config-secret-token) options to the corresponding values found in the APM & Fleet section of {{ecloud}}.

Configure the agent, for example via `php.ini`:

```ini
elastic_apm.server_url=APM_SERVER_URL
elastic_apm.secret_token=TOKEN
elastic_apm.service_name=SERVICE_NAME
```


## Control the amount of APM data emitted by the agent [configure-apm-data-amount]

The following configuration settings control the amount of APM data emitted by the agent. They can be tuned to reduce the agent overhead on the monitored application, network utilization, and the amount of storage required by {{es}}.

* [`transaction_sample_rate`](/reference/configuration-reference.md#config-transaction-sample-rate)
* [`breakdown_metrics`](/reference/configuration-reference.md#config-breakdown-metrics)
* [PHP errors as APM error events](#configure-php-error-reporting)
* [`capture_errors`](/reference/configuration-reference.md#config-capture-errors)


## Logging [configure-logging]

::::{note}
Configuration settings described in this section are related to logs emitted by the agent itself and not the logs emitted by the monitored application. The intended use case for these configuration settings is for the agent’s supportability. The logs emitted by the agent are not stored in Elasticsearch by default, so reducing the level for these logs will not reduce the amount of storage used in Elasticsearch-- see [Control the amount of APM data emitted by the agent](#configure-apm-data-amount) instead.
::::


The easiest way to configure the logging is by using the [`log_level_syslog`](/reference/configuration-reference.md#config-log-level-syslog) configuration option.

Available log levels are:

```text
OFF
CRITICAL
ERROR
WARNING
INFO
DEBUG
TRACE
```

For example, if you specify a `WARNING` log level, only log records with levels `WARNING`, `ERROR`, and `CRITICAL` will be emitted.

`OFF` is only used to disable agent logging.

The agent supports logging to the following sinks: syslog and stderr. Control the level of logging for individual sinks with the [`log_level_syslog`](/reference/configuration-reference.md#config-log-level-syslog) and [`log_level_stderr`](/reference/configuration-reference.md#config-log-level-stderr) options. When a sink-specific logging level is not explicitly set, the fallback setting [`log_level`](/reference/configuration-reference.md#config-log-level) will be used.

For example, the following configuration sets the log level to `WARNING` for all the sinks:

```ini
elastic_apm.log_level=WARNING
```

Alternatively, the following configuration sets log level to `WARNING` for all the sinks except for `syslog`, where the log level is set to `TRACE`.

```ini
elastic_apm.log_level=WARNING
elastic_apm.log_level_syslog=TRACE
```


## PHP errors as APM error events [configure-php-error-reporting]

The agent automatically creates APM error events for PHP errors triggered by the monitored application. APM error events are created only for PHP errors which level is included in PHP [`error_reporting`](https://www.php.net/manual/en/function.error-reporting.php) setting.

In addition [`capture_errors`](/reference/configuration-reference.md#config-capture-errors) configuration option controls if the agent captures any PHP errors.


## Duration format [configure-duration-format]

The *duration* format is used for configuration options like timeouts. The units are provided as a suffix either directly after the number or separated by any amount of whitespace. The units are case insensitive so they can be provided in either lower, upper or even mixed case.

**Supported units:**

* `ms` (milliseconds)
* `s` (seconds)
* `m` (minutes)

**Example:** `5m` (interpreted as 5 minutes) or `10 mS` (interpreted as 10 milliseconds).

**Default units:** Each configuration option with value specifiying a duration has default units. This allows omitting the units in option value. For example if option’s default units are `s` (seconds) then value `10` is interpreted as 10 seconds.

::::{note}
Different configuration options might have different default units so it’s always preferable to provide units explicitly.
::::



## Size format [configure-size-format]

The *size* format is used for options such as maximum buffer sizes. The units are provided as a suffix either directly after the number or separated by any amount of whitespace. The units are case insensitive so they can be provided in either lower, upper or even mixed case.

**Supported units:**

* B (bytes)
* KB (kilobytes)
* MB (megabytes)
* GB (gigabytes)

::::{note}
We use the power-of-two sizing convention, e.g. 1KB = 1024B.
::::


**Example:** `34KB` (interpreted as 34 kilobytes) or `78 mB` (interpreted as 78 megabytes).

**Default units:** Each configuration option with value specifiying a size has default units. This allows omitting the units in option value. For example if option’s default units are `KB` (kilobytes) then value `10` is interpreted as 10 kilobytes.

::::{note}
Different configuration options might have different default units so it’s always preferable to provide units explicitly.
::::



## Wildcard [configure-wildcard]

Some options (for example [`url_groups`](/reference/configuration-reference.md#config-url-groups)) support use of wildcard. A valid value for such configuration options is a comma separated list of wildcard expressions. Only the wildcard `*`, which matches zero or more characters, is supported.

Examples: `*foo*`, `/foo/*/bar, /*/baz*`.

Matching is case insensitive by default. Prepending an element with `(?-i)` makes the matching case sensitive. For example `(?-i)/bar, /foo` matches `/bar` and `/FOO` but it doesn’t match `/BAR`. On the other hand `(?-i)/bar, (?-i)/foo` matches `/bar` and `/foo` but doesn’t match neither `/BAR` nor `/FOO`.

Whitespace around commas separating wildcard expressions in the list is ignored. For example `foo , bar` is the same as `foo,bar`. On the other hand whitespace inside wildcard expressions is significant. For example `*a b*` matches a string only if it contains `a` followed by space and then `b`.

The input string is matched against wildcard expressions in the order they are listed and the first expression that matches is selected.

When configuration option is intended to matched against a input URL (for example [`url_groups`](/reference/configuration-reference.md#config-url-groups) and [`transaction_ignore_urls`](/reference/configuration-reference.md#config-transaction-ignore-urls)) only path part of the URL is tested against wildcard expressions. Other parts of the URL (such as query string, etc.) are not taken into account so including them in the wildcard expressions might lead to unexpected result. For example `/user/*` matches `http://my_site.com/user/Alice?lang=en` while `/user/*?lang=*` does not match `http://my_site.com/user/Alice?lang=en`


