---
mapped_pages:
  - https://www.elastic.co/guide/en/apm/agent/php/current/supported-technologies.html
applies_to:
  stack:
  serverless:
    observability:
  product:
    apm_agent_php: ga
---

# Supported technologies [supported-technologies]

If the agent doesn’t support your favorite technology yet, you can vote for it by participating in [our survey](https://docs.google.com/forms/d/e/1FAIpQLSf8c3BJVMqaeuqpq-t3_Q4NilNcdsrzK1qJ4Qo9JpJslrmYzA/viewform). We will use the results to add support for the most requested technologies.


## Operating systems [supported-os]

We officially support Linux systems (glibc, deb and rpm packages) and Alpine Linux (musl libc - apk packages) for x86_64 (AMD64) processors. Experimentally, we also provide packages for the ARM64 architecture - please note that these packages have not been fully tested.


## PHP versions [supported-php-versions]

The agent supports PHP versions 7.2-8.4.


## Unsupported PHP Server API’s (SAPI) [unsupported-php-sapis]

Currenly we’re not supporting `phpdbg` - agent extension can be loaded but will remain non-functional


## Web frameworks [supported-web-frameworks]

Automatic instrumentation for a web framework means a transaction is automatically created for each incoming request and it is named after the registered route.

We support automatic instrumentation for the following web frameworks.

| Framework | Supported versions |
| --- | --- |
| Framework-less PHP application (i.e., application using PHP’s built in web support) |  |
| Laravel | 6, 7, 8, 9, 10 |
| WordPress | 5, 6 |


## Data access technologies [supported-data-access-technologies]

We support automatic instrumentation for the following data access technologies.

| Data access technology | Supported versions | Notes |
| --- | --- | --- |
| PHP Data Objects (PDO) | any version bundled with a supported PHP version | The agent automatically creates DB spans for all your PDO queries. This includes PDO queries executed by object relational mappers (ORM) like Doctrine & Eloquent. |
| MySQLi | any version bundled with a supported PHP version |  |


## HTTP clients [supported-http-clients]

Automatic instrumentation for an HTTP client technology means an HTTP span is automatically created for each outgoing HTTP request and distributed tracing headers are propagated. The spans are named after the schema `<method> <host>`, for example `GET elastic.co`.

| Framework | Supported versions |
| --- | --- |
| `curl` extension |  |
| `Guzzle` library |  |


## Capturing PHP errors as APM error events [supported-php-errors]

The agent automatically creates APM error events for PHP errors triggered by the monitored application. See [PHP errors as APM error events](/reference/configuration.md#configure-php-error-reporting) for the relevant configuration settings.

