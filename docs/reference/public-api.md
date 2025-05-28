---
mapped_pages:
  - https://www.elastic.co/guide/en/apm/agent/php/current/public-api.html
---

# Public API [public-api]

The public API of the Elastic APM PHP agent lets you customize and manually create spans and transactions.

* [ElasticApm](#api-elasticapm-class) - Public API entry point
* [TransactionInterface](#api-transaction-interface)
* [SpanInterface](#api-span-interface)
* [Manual distributed tracing](#api-manual-distributed-tracing)


## ElasticApm [api-elasticapm-class]

This is the entry point of the public API. It allows to start transactions, gives you access to the current transaction, etc.

To use the API, you invoke the static methods on the class `\Elastic\Apm\ElasticApm`.


### `ElasticApm::beginCurrentTransaction` [api-elasticapm-class-begin-current-transaction]

Begins a new transaction and sets it as the current transaction. Use this method to create a custom transaction. Note that when automatic instrumentation is used the agent begins a new transaction automatically whenever your application receives an incoming HTTP request so you only need to use this method to create custom transactions.

::::{note}
You must call [`TransactionInterface->end`](#api-transaction-interface-end) when the transaction has ended.
::::


The best practice is to use a `try`-`finally` block. For example:

```php
use Elastic\Apm\ElasticApm;

$transaction = ElasticApm::beginCurrentTransaction(
    'transaction_name',
    'transaction_type'
);
try {
    // do your thing ...
} finally {
    $transaction->end();
}
```

See [TransactionInterface](#api-transaction-interface) on how to customize a transaction.

### `ElasticApm::createErrorFromThrowable` [api-elasticapm-class-create-error-from-throwable]

Captures a throwable exception. This API:
* Captures an error as an exception
* Displays it in the ElasticAPM `Errors` screen
* Allows for easy tracking of caught exceptions.

For example:
```php
use Elastic\Apm\ElasticApm;

private function captureExceptionFromErrorMessage($message)
{
    try {
        throw new Exception($message);
    } catch (Throwable $ex) {
        ElasticApm::createErrorFromThrowable($ex);
    }
}
```

The code above would allow for an error message to be tracked via an artificially created exception.

### `ElasticApm::captureCurrentTransaction` [api-elasticapm-class-capture-current-transaction]

This is a convenience API that ensures [`TransactionInterface->end`](#api-transaction-interface-end) is called when the transaction has ended. This API:

* Begins a new transaction
* Sets the new transaction as the current transaction
* Executes the provided `callable` as the new transaction
* Ends the new transaction
* Returns the value returned by the provided `callable`

For example:

```php
use Elastic\Apm\ElasticApm;
use Elastic\Apm\TransactionInterface;

ElasticApm::captureCurrentTransaction(
    'transaction_name',
    'transaction_type',
    function (TransactionInterface $transaction) {
        // do your thing...
    }
);
```

See [TransactionInterface](#api-transaction-interface) on how to customize a transaction.


### `ElasticApm::getCurrentTransaction` [api-elasticapm-class-get-current-transaction]

Returns the current transaction.

```php
use Elastic\Apm\ElasticApm;

$transaction = ElasticApm::getCurrentTransaction();
```

See [TransactionInterface](#api-transaction-interface) on how to customize a transaction.


## TransactionInterface [api-transaction-interface]

A transaction describes an event captured by an Elastic APM agent monitoring a service. Transactions help combine multiple [Spans](#api-span-interface) into logical groups, and they are the first [Span](#api-span-interface) of a service. More information on Transactions and Spans is available in the [APM data model](docs-content://solutions/observability/apps/learn-about-application-data-types.md) documentation.

See [`ElasticApm::getCurrentTransaction`](#api-elasticapm-class-get-current-transaction) on how to get a reference to the current transaction.


### `TransactionInterface->getCurrentSpan` [api-transaction-interface-get-current-span]

Returns the current span for this transaction.

Example:

```php
$span = $transaction->getCurrentSpan();
```


### `TransactionInterface->beginCurrentSpan` [api-transaction-interface-begin-current-span]

Begins a new span with the current span as the new span’s parent and sets the new span as the current span for this transaction. If this transaction’s doesn’t have the current span then the transaction itself is set as the new span’s parent.

::::{note}
You must call [`SpanInterface->end`](#api-span-interface-end) when the span has ended.
::::


The best practice is to use a `try`-`finally` block. For example:

```php
$span = $transaction->beginCurrentSpan(
    'span_name',
    'span_type',
    'span_sub-type', // optional
    'span_action' // optional
);
try {
    // do your thing ...
} finally {
    $span->end();
}
```


### `TransactionInterface->captureCurrentSpan` [api-transaction-interface-capture-current-span]

This is a convenience API that ensures [`SpanInterface->end`](#api-span-interface-end) is called when the span has ended. This API

* Begins a new span with this transaction’s current span as the new span’s parent and sets the new span as the current span for this transaction. If this transaction’s doesn’t have a current span then the transaction itself is set as the new span’s parent.
* Executes the provided `callable` as the new span
* Ends the new transaction
* Returns the value returned by the provided `callable`

For example:

```php
$parentSpan->captureCurrentSpan(
    'span_name',
    'span_type',
    function (SpanInterface $childSpan) {
        // do your thing...
    },
    'span_sub-type', // optional
    'span_action' // optional
);
```


### `TransactionInterface->beginChildSpan` [api-transaction-interface-begin-child-span]

Begins a new span with this transaction as the new span’s parent.

::::{note}
You must call [`SpanInterface->end`](#api-span-interface-end) when the span has ended.
::::


The best practice is to use `try`-`finally` block. For example:

```php
$span = $transaction->beginChildSpan(
    'span_name',
    'span_type',
    'span_sub-type', // optional
    'span_action' // optional
);
try {
    // do your thing ...
} finally {
    $span->end();
}
```


### `TransactionInterface->captureChildSpan` [api-transaction-interface-capture-child-span]

This is a convenience API that ensures [`SpanInterface->end`](#api-span-interface-end) is called when the span has ended. This API

* Begins a new span with this transaction as the new span’s parent
* Executes the provided `callable` as the new span and
* Ends the new span
* Returns the value returned by the provided `callable`

For example:

```php
$transaction->captureChildSpan(
    'span_name',
    'span_type',
    function (SpanInterface $span) {
        // do your thing...
    },
    'span_sub-type', // optional
    'span_action' // optional
);
```


### `TransactionInterface->setName` [api-transaction-interface-set-name]

Sets the name of the transaction. Transaction name is generic designation of a transaction in the scope of a single service (e.g., `GET /users/:id`).

Example:

```php
$transaction->setName('GET /users/:id');
```


### `TransactionInterface->setType` [api-transaction-interface-set-type]

Sets the type of the transaction. Transaction type is a keyword of specific relevance in the service’s domain. For example `request`, `backgroundjob`, etc.

Example:

```php
$transaction->setType('my custom transaction type');
```


### `TransactionInterface->context()->setLabel` [api-transaction-interface-set-label]

Sets a label by a key. Labels are a flat mapping of user-defined string keys and string, number, or boolean values.

::::{note}
The labels are indexed in Elasticsearch so that they are searchable and aggregatable. Take special care when using user provided data, like URL parameters, as a label key because it can lead to [Elasticsearch mapping explosion](docs-content://manage-data/data-store/mapping.md#mapping-limit-settings).
::::


Example:

```php
$transaction->context()->setLabel('my label with string value', 'some text');
$transaction->context()->setLabel('my label with int value', 123);
$transaction->context()->setLabel('my label with float value', 4.56);
```


### `TransactionInterface->getId` [api-transaction-interface-get-id]

Gets the ID of the transaction. Transaction ID is a hex encoded 64 random bits (== 8 bytes == 16 hex digits) ID.

If this transaction represents a noop, this method returns an unspecified dummy ID.

Example:

```php
$transactionId = $transaction->getId();
```


### `TransactionInterface->getTraceId` [api-transaction-interface-get-trace-id]

Gets the trace ID of the transaction. Trace ID is a hex encoded 128 random bits (== 16 bytes == 32 hex digits) ID of the correlated trace.

The trace ID is consistent across all transactions and spans which belong to the same logical trace, even for transactions and spans which happened in another service (given this service is also monitored by Elastic APM).

If this transaction represents a noop, this method returns an unspecified dummy ID.

Example:

```php
$traceId = $transaction->getTraceId();
```


### `TransactionInterface->getParentId` [api-transaction-interface-get-parent-id]

Gets ID of the parent transaction or span.

See [`TransactionInterface->getId`](#api-transaction-interface-get-id) and [`SpanInterface->getId`](#api-span-interface-get-id).

The root transaction of a trace does not have a parent, so `null` is returned.

If this transaction represents a noop, this method returns an unspecified dummy ID.

Example:

```php
$parentId = $transaction->getParentId();
```


### `TransactionInterface->ensureParentId()` [api-transaction-interface-ensure-parent-id]

If the transaction does not have a parent-ID yet, calling this method generates a new ID, sets it as the parent-ID of this transaction, and returns it as a `string`.

This enables the correlation of the spans the JavaScript Real User Monitoring (RUM) agent creates for the initial page load with the transaction of the backend service. If your backend service generates the HTML page dynamically, initializing the JavaScript RUM agent with the value of this method allows analyzing the time spent in the browser vs in the backend services.

An example of using this API in Laravel application can be found at [https://github.com/elastic/opbeans-php/](https://github.com/elastic/opbeans-php/).

Add `isElasticApmEnabled`, `elasticApmCurrentTransaction` properties to the view ([see the relevant part in opbeans-php’s `AppServiceProvider.php`](https://github.com/elastic/opbeans-php/blob/22df4af76a879d8ce7237d90e953e312fb98e792/app/Providers/AppServiceProvider.php#L33)) and add a snippet similar to the following one to the body of your HTML page, preferably before other JS libraries ([see opbeans-php’s `rendered_by_frontend.blade.php`](https://github.com/elastic/opbeans-php/blob/22df4af76a879d8ce7237d90e953e312fb98e792/resources/views/rendered_by_frontend.blade.php)) :

```html
@if ($isElasticApmEnabled)
    <script>
        window.rumConfig = {
            serviceName: "{{ $elasticApmJsServiceName }}",
            serviceVersion: "{{ $elasticApmJsServiceVersion }}",
            serverUrl: "{{ $elasticApmJsServerUrl }}",
            pageLoadTraceId: "{{ $elasticApmCurrentTransaction->getTraceId() }}",
            pageLoadSpanId: "{{ $elasticApmCurrentTransaction->ensureParentId() }}",
            pageLoadSampled: {{ $elasticApmCurrentTransaction->isSampled() ? "true" : "false" }}
        }
    </script>
@endif
```

See the [JavaScript RUM agent documentation](apm-agent-rum-js://reference/index.md) for more information.


### `TransactionInterface->setResult` [api-transaction-interface-set-result]

Sets the result of the transaction.

Transaction result is optional and can be set to `null`. For HTTP-related transactions, the result is HTTP status code formatted like `HTTP 2xx`.

Example:

```php
$transaction->setResult('my custom transaction result');
```


### `TransactionInterface->end` [api-transaction-interface-end]

Ends the transaction and queues it to be reported to the APM Server.

It is illegal to call any mutating methods (for example any `set...` method is a mutating method) on a transaction instance which has already ended.

Example:

```php
$transaction->end();
```


## SpanInterface [api-span-interface]

A span contains information about a specific code path, executed as part of a transaction.

If for example a database query happens within a recorded transaction, a span representing this database query may be created. In such a case the name of the span will contain information about the query itself, and the type will hold information about the database type.

See [`TransactionInterface->getCurrentSpan`](#api-transaction-interface-get-current-span) on how to get the current span.


### `SpanInterface->setName` [api-span-interface-set-name]

Sets the name of the span. Span name is generic designation of a span in the scope of a transaction.

Example:

```php
$span->setName('SELECT FROM customer');
```


### `SpanInterface->setType` [api-span-interface-set-type]

Sets the type of the span. Span type is a keyword of specific relevance in the service’s domain. For example `db`, `external`, etc.

Example:

```php
$span->setType('my custom span type');
```


### `SpanInterface->setSubtype` [api-span-interface-set-subtype]

Sets the sub-type of the span. Span sub-type is a further sub-division of the type. For example, `mysql`, `postgresql`, or `elasticsearch` for the type `db`, `http` for the type `external`, etc.

Span sub-type is optional and can be set to `null`. Span sub-type default value is `null`.

Example:

```php
$span->setSubtype('my custom span sub-type');
```


### `SpanInterface->setAction` [api-span-interface-set-action]

Sets the action of the span. Span action is the specific kind of event within the sub-type represented by the span. For example `query` for type/sub-type `db`/`mysql`, `connect` for type/sub-type `db`/`cassandra`, etc.

Span action is optional and can be set to `null`. Span action default value is `null`.

Example:

```php
$span->setAction('my custom span action');
```


### `SpanInterface->context()->setLabel` [api-span-interface-set-label]

Sets a label by a key. Labels are a flat mapping of user-defined string keys and string, number, or boolean values.

::::{note}
The labels are indexed in Elasticsearch so that they are searchable and aggregatable. Take special care when using user provided data, like URL parameters, as a label key because it can lead to [Elasticsearch mapping explosion](docs-content://manage-data/data-store/mapping.md#mapping-limit-settings).
::::


Example:

```php
$span->context()->setLabel('my label with string value', 'some text');
$span->context()->setLabel('my label with int value', 123);
$span->context()->setLabel('my label with float value', 4.56);
```


### `SpanInterface->getId` [api-span-interface-get-id]

Gets the ID of the span. Span ID is a hex encoded 64 random bits (== 8 bytes == 16 hex digits) ID.

If this span represents a noop, this method returns an unspecified dummy ID.

Example:

```php
$spanId = $span->getId();
```


### `SpanInterface->getTraceId` [api-span-interface-get-trace-id]

Gets the trace ID of the span. Trace ID is a hex encoded 128 random bits (== 16 bytes == 32 hex digits) ID of the correlated trace.

The trace ID is consistent across all transactions and spans which belong to the same logical trace, even for transactions and spans which happened in another service (given this service is also monitored by Elastic APM).

If this span represents a noop, this method returns an unspecified dummy ID.

Example:

```php
$traceId = $span->getTraceId();
```


### `SpanInterface->getTransactionId` [api-span-interface-get-transaction-id]

Gets ID of the correlated transaction. See [`TransactionInterface->getId`](#api-transaction-interface-get-id).

If this span represents a noop, this method returns an unspecified dummy ID.

Example:

```php
$transactionId = $span->getTransactionId();
```


### `SpanInterface->getParentId` [api-span-interface-get-parent-id]

Gets ID of the parent transaction or span. If this span is the root span of the correlated transaction then its parent is the correlated transaction, otherwise, its parent is the parent span. See [`TransactionInterface->getId`](#api-transaction-interface-get-id) and [`SpanInterface->getId`](#api-span-interface-get-id).

If this span represents a noop, this method returns an unspecified dummy ID.

Example:

```php
$parentId = $span->getParentId();
```


### `SpanInterface->beginChildSpan` [api-span-interface-begin-child-span]

Begins a new span with this span as the new span’s parent.

::::{note}
You must call [`SpanInterface->end`](#api-span-interface-end) when the span has ended.
::::


The best practice is to use a `try`-`finally` block. For example:

```php
$childSpan = $parentSpan->beginChildSpan(
    'span_name',
    'span_type',
    'span_sub-type', // optional
    'span_action' // optional
);
try {
    // do your thing ...
} finally {
    $childSpan->end();
}
```


### `SpanInterface->captureChildSpan` [api-span-interface-capture-child-span]

This is a convenience API that ensures [`SpanInterface->end`](#api-span-interface-end) is called when the span has ended. This API

* Begins a new span with this span as the new span’s parent
* Executes the provided `callable` as the new span
* Ends the new span
* Returns the value returned by the provided `callable`

For example:

```php
$parentSpan->captureChildSpan(
    'span_name',
    'span_type',
    function (SpanInterface $childSpan) {
        // do your thing...
    },
    'span_sub-type', // optional
    'span_action' // optional
);
```


### `SpanInterface->end` [api-span-interface-end]

Ends the span and queues it to be reported to the APM Server.

It is illegal to call any mutating methods (for example any `set...` method is a mutating method) on a span instance which has already ended.

Example:

```php
$span->end();
```


## Manual distributed tracing [api-manual-distributed-tracing]

Elastic APM PHP agent automatically propagates distributed tracing context for [supported technologies](/reference/supported-technologies.md). If your service communicates over a different, unsupported protocol, you can manually propagate distributed tracing context from a sending service to a receiving service using the agent’s API.

Distributed tracing data consists of multiple key-value pairs. For example for HTTP protocol these pairs are passed as request headers.

At the sending service you must add key-value pairs to the outgoing request. Use `injectDistributedTracingHeaders()` API to get the distributed tracing data from the corresponding instance of [SpanInterface](#api-span-interface) or [TransactionInterface](#api-transaction-interface)

For example assuming the outgoing request is associated with `$span`  :

```php
$span->injectDistributedTracingHeaders(
    function (string $headerName, string $headerValue) use ($myRequest): void {
        $myRequest->addHeader($headerName, $headerValue);
    }
);
```

At the receiving service you must pass key-value pairs from the sending side to `ElasticApm::newTransaction` API.

Example:

```php
$myTransaction = ElasticApm::newTransaction('my TX name', 'my TX type')
    ->distributedTracingHeaderExtractor(
        function (string $headerName) use ($myRequest): ?string {
            return $myRequest->hasHeader($headerName)
                ? $myRequest->getHeader($headerName)
                : null;
        }
    )->begin();
```

