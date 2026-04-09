---
mapped_pages:
  - https://www.elastic.co/guide/en/apm/agent/php/current/configuration-reference.html
applies_to:
  stack:
  serverless:
    observability:
  product:
    apm_agent_php: ga
---

# Configuration reference [configuration-reference]


## `api_key` [config-api-key]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_API_KEY` | `elastic_apm.api_key` |

| Default | Type |
| --- | --- |
| None | String |

This string is used to ensure that only your agents can send data to your APM Server. You must have created the API key using the APM Server [command line tool](docs-content://solutions/observability/apps/api-keys.md).

[`api_key`](#config-api-key) is an alternative to [`secret_token`](#config-secret-token). If both [`secret_token`](#config-secret-token) and [`api_key`](#config-api-key) are configured, then [`api_key`](#config-api-key) has precedence and [`secret_token`](#config-secret-token) is ignored.

::::{note}
This feature is fully supported in the APM Server versions >= 7.6.
::::


::::{warning}
The `api_key` value is sent as plain-text in every request to the server, so you should also secure your communications using HTTPS. Unless you do so, your API Key could be observed by an attacker.
::::



## `breakdown_metrics` [config-breakdown-metrics]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_BREAKDOWN_METRICS` | `elastic_apm.breakdown_metrics` |

| Default | Type |
| --- | --- |
| true | Boolean |

If this configuration option is set to `true` the agent will collect and report breakdown metrics (`span.self_time`) used for "Time spent by span type" chart. Set it to `false` to disable the collection and reporting of breakdown metrics, which can reduce the overhead of the agent.

::::{note}
This feature requires APM Server and Kibana >= 7.3.
::::



## `capture_errors` [config-capture-errors]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_CAPTURE_ERRORS` | `elastic_apm.capture_errors` |

| Default | Type |
| --- | --- |
| true | Boolean |

If this configuration option is set to `true` the agent will collect and report error events. Set it to `false` to disable the collection and reporting of APM error events, which can reduce the overhead of the agent.

Also see [PHP errors as APM error events](/reference/configuration.md#configure-php-error-reporting).



## `capture_errors_with_php_part` [config-capture-errors-with-php-part]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_CAPTURE_ERRORS_WITH_PHP_PART` | `elastic_apm.capture_errors_with_php_part` |

| Default | Type |
|---------| --- |
| false   | Boolean |

If this configuration option is set to `false` (the default) the agent will capture errors and exceptions using native API.
If this configuration option is set to `true` the agent will capture errors and exceptions using PHP user-land API.

Also see [PHP errors as APM error events](/reference/configuration.md#configure-php-error-reporting).



## `capture_exceptions` [config-capture-exceptions]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_CAPTURE_EXCEPTIONS` | `elastic_apm.capture_exceptions` |

| Default | Type |
| --- | --- |
| true | Boolean |

If this configuration option is set to `true` the agent will capture exceptions and report error events for transaction with failure outcome.
If this configuration option is not set then [`capture_errors`](#config-capture-errors) takes effect.
Set it to `true`/`false` to enable/disable the collection of exceptions and reporting them as APM error events regardless of [`capture_errors`](#config-capture-errors).

Also see [PHP errors as APM error events](/reference/configuration.md#configure-php-error-reporting).


## `disable_instrumentations` [config-disable-instrumentations]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_DISABLE_INSTRUMENTATIONS` | `elastic_apm.disable_instrumentations` |

| Default | Type |
| --- | --- |
| empty list | List of strings |

A comma-separated list of wildcard expressions to match instrumentation names which should be disabled. When an instrumentation is disabled, no spans will be created for that instrumentation. Each instrumentation has a name and any number of keywords. If the instrumentation’s name or any of its keywords match this configuration option then the instrumentation is disabled.

See [Wildcard](/reference/configuration.md#configure-wildcard) for more details on how to use wildcard expressions.

Supported instrumentations:

| Name | Keywords |
| --- | --- |
| `curl` | `HTTP-client` |
| `PDO` | `DB` |
| `MySQLi` | `DB` |

Examples:

* `db` disables both PDO and MySQLi instrumentations
* `*HTTP*` disables curl instrumentation


## `disable_send` [config-disable-send]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_DISABLE_SEND` | `elastic_apm.disable_send` |

| Default | Type |
| --- | --- |
| false | Boolean |

If set to `true`, the agent will work as usual, except for any task requiring communication with the APM server. Events will be dropped and the agent won’t be able to receive central configuration, which means that any other configuration cannot be changed in this state without restarting the service.  Example uses for this setting are: maintaining the ability to create traces and log trace/transaction/span IDs through the log correlation feature, and getting automatic distributed tracing via the [W3C HTTP headers](https://w3c.github.io/trace-context/).


## `enabled` [config-enabled]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_ENABLED` | `elastic_apm.enabled` |

| Default | Type |
| --- | --- |
| true | Boolean |

Setting to false will completely disable the agent.


## `environment` [config-environment]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_ENVIRONMENT` | `elastic_apm.environment` |

| Default | Type |
| --- | --- |
| None | String |

The name of the environment this service is deployed in, e.g. "production" or "staging".

Environments allow you to easily filter data on a global level in the APM app. It’s important to be consistent when naming environments across agents. See [environment selector](docs-content://solutions/observability/apps/filter-application-data.md#apm-filter-your-data-service-environment-filter) in the Kibana UI for more information.

::::{note}
This feature is fully supported in the APM app in Kibana versions >= 7.2. You must use the query bar to filter for a specific environment in versions prior to 7.2.
::::



## `global_labels` [config-global-labels]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_GLOBAL_LABELS` | `elastic_apm.global_labels` |

| Default | Type |
| --- | --- |
| empty map | string to string map |

Labels from this configuration are added to all the entities produced by the agent.

The format is `key=value[,key=value[,...]]`. For example `dept=engineering,rack=number8`.

::::{note}
When setting this configuration option in `.ini` file it is required to enclose the value in quotes (because the value contains equal sign). For example `elastic_apm.global_labels = "dept=engineering,rack=number8"`
::::


Any labels set by the application via the agent’s public API will override global labels with the same keys.

::::{note}
This option requires APM Server 7.2 or later. It will have no effect on older versions.
::::



## `hostname` [config-hostname]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_HOSTNAME` | `elastic_apm.hostname` |

| Default | Type |
| --- | --- |
| the local machine’s host name | String |

This option allows for the reported host name to be configured. If this option is not set the local machine’s host name is used.


## `log_level` [config-log-level]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_LOG_LEVEL` | `elastic_apm.log_level` |

| Default | Type |
| --- | --- |
| None | Log level |

A fallback configuration setting to control the logging level for the agent. Only used when a sink-specific option is not explicitly set. See [Logging](/reference/configuration.md#configure-logging) for details.


## `log_level_stderr` [config-log-level-stderr]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_LOG_LEVEL_STDERR` | `elastic_apm.log_level_stderr` |

| Default | Type |
| --- | --- |
| `CRITICAL` | Log level |

The logging level for `stderr` logging sink. See [Logging](/reference/configuration.md#configure-logging) for details.


## `log_level_syslog` [config-log-level-syslog]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_LOG_LEVEL_SYSLOG` | `elastic_apm.log_level_syslog` |

| Default | Type |
| --- | --- |
| `INFO` | Log level |

The logging level for `syslog` logging sink. See [Logging](/reference/configuration.md#configure-logging) for details.


## `profiling_inferred_spans_enabled` [config-profiling-inferred-spans-enabled]

::::{warning}
This functionality is in technical preview and may be changed or removed in a future release. Elastic will work to fix any issues, but features in technical preview are not subject to the support SLA of official GA features.
::::


| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_PROFILING_INFERRED_SPANS_ENABLED` | `elastic_apm.profiling_inferred_spans_enabled` |

| Default | Type |
| --- | --- |
| `false` | Boolean |

If this option is set to `true` then the agent creates spans for method executions based on sampling aka statistical profiler.

Due to the nature of how sampling profilers work, the duration of the inferred spans are not exact, but only estimations. See [`profiling_inferred_spans_sampling_interval`](#config-profiling-inferred-spans-sampling-interval) to fine tune the trade-off between accuracy and overhead.


## `profiling_inferred_spans_min_duration` [config-profiling-inferred-spans-min-duration]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_PROFILING_INFERRED_SPANS_MIN_DURATION` | `elastic_apm.profiling_inferred_spans_min_duration` |

| Default | Type |
| --- | --- |
| `0ms` | Duration |

The minimum duration of an inferred span.

Note that effective minimum duration is also affected by the sampling interval so it is max([`profiling_inferred_spans_min_duration`](#config-profiling-inferred-spans-min-duration), [`profiling_inferred_spans_sampling_interval`](#config-profiling-inferred-spans-sampling-interval))

This configuration option supports the duration suffixes: `ms`, `s` and `m`. For example: `100ms`.


## `profiling_inferred_spans_sampling_interval` [config-profiling-inferred-spans-sampling-interval]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_PROFILING_INFERRED_SPANS_SAMPLING_INTERVAL` | `elastic_apm.profiling_inferred_spans_sampling_interval` |

| Default | Type |
| --- | --- |
| `50ms` | Duration |

The frequency at which stack traces are gathered in order to construct inferred spans. The lower this is set, the more accurate the inferred spans durations will be. On the other hand higher accuracy comes at the expense of higher overhead and more inferred spans for potentially irrelevant operations (see [`profiling_inferred_spans_min_duration`](#config-profiling-inferred-spans-min-duration)).

This configuration option supports the duration suffixes: `ms`, `s` and `m`. For example: `50ms`.


## `secret_token` [config-secret-token]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_SECRET_TOKEN` | `elastic_apm.secret_token` |

| Default | Type |
| --- | --- |
| None | String |

This string is used to ensure that only your agents can send data to your APM Server. Both the agents and the APM Server have to be configured with the same secret token.

See [the relevant APM Server’s documentation](docs-content://solutions/observability/apps/secret-token.md) on how to configure APM Server’s secret token.

Use this setting if the APM Server requires a token, like in {{ess}}.

[`secret_token`](#config-secret-token) is an alternative to [`api_key`](#config-api-key). If both [`secret_token`](#config-secret-token) and [`api_key`](#config-api-key) are configured then [`api_key`](#config-api-key) has precedence and [`secret_token`](#config-secret-token) is ignored.

::::{warning}
The `secret_token` is sent as plain-text in every request to the server, so you should also secure your communications using HTTPS. Unless you do so, your secret token could be observed by an attacker.
::::



## `server_timeout` [config-server-timeout]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_SERVER_TIMEOUT` | `elastic_apm.server_timeout` |

| Default | Type |
| --- | --- |
| `30s` | Duration |

If a request sending events to the APM server takes longer than the configured timeout, the request is canceled and the events are discarded.

The value has to be provided in **[duration format](/reference/configuration.md#configure-duration-format)**.

This option’s default unit is `s` (seconds).

If the value is `0` (or `0ms`, `0s`, etc.) the timeout for sending events to the APM Server is disabled.

Negative values are invalid and result in the default value being used instead.


## `server_url` [config-server-url]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_SERVER_URL` | `elastic_apm.server_url` |

| Default | Type |
| --- | --- |
| `http://localhost:8200` | String |

The URL for your APM Server. The URL must be fully qualified, including protocol (`http` or `https`) and port.


## `service_name` [config-service-name]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_SERVICE_NAME` | `elastic_apm.service_name` |

| Default | Type |
| --- | --- |
| `unknown-php-service` | String |

This is used to keep all the errors and transactions of your service together and is the primary filter in the Elastic APM user interface.

::::{note}
The service name must conform to this regular expression: `^[a-zA-Z0-9 _-]+$`. In other words, a service name must only contain characters from the ASCII alphabet, numbers, dashes, underscores, and spaces. Characters in service name that don’t match regular expression will be replaced by `_` (underscore) character.
::::



## `service_node_name` [config-service-node-name]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_SERVICE_NODE_NAME` | `elastic_apm.service_node_name` |

| Default | Type |
| --- | --- |
| None | String |

If it’s set, this name is used to distinguish between different nodes of a service. If it’s not set, data aggregations will be done based on the container ID if the monitored application runs in a container. Otherwise data aggregations will be done based on the reported hostname (automatically discovered or manually configured using [`hostname`](#config-hostname)).


## `service_version` [config-service-version]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_SERVICE_VERSION` | `elastic_apm.service_version` |

| Default | Type |
| --- | --- |
| None | String |

The version of the currently deployed service. If your deployments are not versioned, the recommended value for this field is the commit identifier of the deployed revision, e.g., the output of git rev-parse HEAD.


## `span_compression_enabled` [config-span-compression-enabled]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_SPAN_COMPRESSION_ENABLED` | `elastic_apm.span_compression_enabled` |

| Default | Type |
| --- | --- |
| true | Boolean |

Setting this option to true will enable span compression feature. Span compression reduces the collection, processing, and storage overhead, and removes clutter from the UI. The tradeoff is that some information such as DB statements of all the compressed spans will not be collected.


## `span_compression_exact_match_max_duration` [config-span-compression-exact-match-max-duration]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION` | `elastic_apm.span_compression_exact_match_max_duration` |

| Default | Type |
| --- | --- |
| `50ms` | Duration |

Consecutive spans that are exact match and that are under this threshold will be compressed into a single composite span. This option does not apply to composite spans. This reduces the collection, processing, and storage overhead, and removes clutter from the UI. The tradeoff is that the DB statements of all the compressed spans will not be collected.

Since it is **max** duration threshold setting this configuration option to 0 effectively disables this compression strategy because only spans with duration 0 will be considered eligible for compression with this strategy.

This configuration option supports the duration suffixes: `ms`, `s` and `m`. For example: `10ms`. This option’s default unit is `ms`, so `5` is interpreted as `5ms`.


## `span_compression_same_kind_max_duration` [config-span-compression-same-kind-max-duration]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_SPAN_COMPRESSION_SAME_KIND_MAX_DURATION` | `elastic_apm.span_compression_same_kind_max_duration` |

| Default | Type |
| --- | --- |
| `0ms` | Duration |

Consecutive spans to the same destination that are under this threshold will be compressed into a single composite span. This option does not apply to composite spans. This reduces the collection, processing, and storage overhead, and removes clutter from the UI. The tradeoff is that the DB statements of all the compressed spans will not be collected.

Since it is **max** duration threshold setting this configuration option to 0 effectively disables this compression strategy because only spans with duration 0 will be considered eligible for compression with this strategy.

This configuration option supports the duration suffixes: `ms`, `s` and `m`. For example: `10ms`. This option’s default unit is `ms`, so `5` is interpreted as `5ms`.


## `span_stack_trace_min_duration` [config-span-stack-trace-min-duration]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_SPAN_STACK_TRACE_MIN_DURATION` | `elastic_apm.span_stack_trace_min_duration` |

| Default | Type |
| --- | --- |
| `5ms` | Duration |

While it might be very helpful to have stack trace attached to a span, collecting stack traces does have some overhead. This configuration controls the minimum span duration at which stack traces are collected. A higher value means lower overhead as stack trace collection is skipped for quick spans.

Set this config to:

* any positive value (e.g. `5ms`) - to limit stack trace collection to spans with duration equal to or greater than the given value (e.g. 5 milliseconds)
* `0` (or `0` with any duration units e.g. `0ms`) - to collect stack traces for spans with any duration
* any negative value (e.g. `-1ms`) - to disable stack trace collection for spans completely

This configuration option supports the duration suffixes: `ms`, `s` and `m`. For example: `10ms`. This option’s default unit is `ms`, so `5` is interpreted as `5ms`.


## `stack_trace_limit` [config-stack-trace-limit]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_STACK_TRACE_LIMIT` | `elastic_apm.stack_trace_limit` |

| Default | Type |
| --- | --- |
| `50` | Integer |

This option controls how many frames are included in stack traces captured by the agent.

Set this config to:

* any positive integer - to define the maximum number of frames included in stack traces
* `0` - to disable stack trace capturing
* any negative integer - to capture all frames


## `transaction_ignore_urls` [config-transaction-ignore-urls]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_TRANSACTION_IGNORE_URLS` | `elastic_apm.transaction_ignore_urls` |

| Default | Type |
| --- | --- |
| empty list | List of wildcard expressions |

This option instructs the agent to ignore requests with certain URLs by not to creating transactions for those requests. It only affects automatic creation of transactions by the agent but user can still create transactions manually by using [agent’s public API](/reference/public-api.md).

See [Wildcard](/reference/configuration.md#configure-wildcard) section for more details on how to use wildcard expressions.


## `transaction_max_spans` [config-transaction-max-spans]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_TRANSACTION_MAX_SPANS` | `elastic_apm.transaction_max_spans` |

| Default | Type |
| --- | --- |
| 500 | Integer |

This limits the amount of spans that are recorded per transaction. This is helpful in cases where a transaction creates a very high amount of spans, for example, thousands of SQL queries. Setting an upper limit helps prevent overloading the Agent and APM server in these edge cases.

If the value is `0` no spans will be collected.

Negative values are invalid and result in the default value being used instead.


## `transaction_sample_rate` [config-transaction-sample-rate]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_TRANSACTION_SAMPLE_RATE` | `elastic_apm.transaction_sample_rate` |

| Default | Type |
| --- | --- |
| 1.0 | Floating-point number |

By default, the agent will sample every transaction (e.g., a request to your service). To reduce overhead and storage requirements, set the sample rate to a value between `0.0` and `1.0`. The agent still records the overall time and result for unsampled transactions, but not context information, labels, or spans.


## `verify_server_cert` [config-verify-server-cert]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_VERIFY_SERVER_CERT` | `elastic_apm.verify_server_cert` |

| Default | Type |
| --- | --- |
| `true` | Boolean |

By default, the agent verifies the SSL certificate if you use an HTTPS connection to the APM server. The verification can be disabled by changing this setting to `false`.


## `url_groups` [config-url-groups]

| Environment variable name | Option name in `php.ini` |
| --- | --- |
| `ELASTIC_APM_URL_GROUPS` | `elastic_apm.url_groups` |

| Default | Type |
| --- | --- |
| empty list | List of wildcard expressions |

With this option, you can group several URL paths together by using wildcard expressions like `/user/*` - this way `/user/Alice` and `/user/Bob` will be mapped to transaction name `/user/*`.

See [Wildcard](/reference/configuration.md#configure-wildcard) section for more details on how to use wildcard expressions.

