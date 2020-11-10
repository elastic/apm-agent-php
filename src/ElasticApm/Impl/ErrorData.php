<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Util\IdGenerator;
use JsonSerializable;
use Throwable;

/**
 * An error or a logged error message captured by an agent occurring in a monitored service
 *
 * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json
 *
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class ErrorData implements JsonSerializable, LoggableInterface
{
    use LoggableTrait;

    /**
     * @var float
     *
     * UTC based and in microseconds since Unix epoch
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L6
     */
    public $timestamp;

    /**
     * @var string
     *
     * Hex encoded 128 random bits ID of the error.
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L9
     */
    public $id;

    /**
     * @var string|null
     *
     * Hex encoded 128 random bits ID of the correlated trace.
     * Must be present if transaction_id and parent_id are set.
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L14
     */
    public $traceId = null;

    /**
     * @var string|null
     *
     * Hex encoded 64 random bits ID of the correlated transaction.
     * Must be present if trace_id and parent_id are set.
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L19
     */
    public $transactionId = null;

    /**
     * @var string|null
     *
     * Hex encoded 64 random bits ID of the parent transaction or span.
     * Must be present if trace_id and transaction_id are set.
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L24
     */
    public $parentId = null;

    /**
     * @var ErrorTransactionData|null
     *
     * Data for correlating errors with transactions
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L29
     */
    public $transaction = null;

    /**
     * @var TransactionContextData|null
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L44
     */
    public $context = null;

    /**
     * @var ErrorExceptionData|null
     *
     * Data for correlating errors with transactions
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L29
     */
    public $exception = null;

    public static function build(
        Tracer $tracer,
        ?Throwable $throwable,
        ?Transaction $transaction,
        ?Span $span
    ): ErrorData {
        $result = new ErrorData();

        $result->timestamp = $tracer->getClock()->getSystemClockCurrentTime();
        $result->id = IdGenerator::generateId(Constants::ERROR_ID_SIZE_IN_BYTES);

        if (!is_null($transaction)) {
            $result->transaction = ErrorTransactionData::build($transaction);
            $result->context = $transaction->cloneContextData();
            $result->traceId = $transaction->getTraceId();
            $result->transactionId = $transaction->getId();
            $result->parentId = is_null($span) ? $transaction->getId() : $span->getId();
        }

        if (!is_null($throwable)) {
            $result->exception = ErrorExceptionData::build($tracer, $throwable);
        }

        return $result;
    }

    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValue(
            'timestamp',
            SerializationUtil::adaptTimestamp($this->timestamp),
            /* ref */ $result
        );
        SerializationUtil::addNameValue('id', $this->id, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('trace_id', $this->traceId, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('transaction_id', $this->transactionId, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('parent_id', $this->parentId, /* ref */ $result);

        if (!is_null($this->transaction)) {
            SerializationUtil::addNameValueIfNotEmpty(
                'transaction',
                $this->transaction->jsonSerialize(),
                /* ref */ $result
            );
        }

        if (!is_null($this->context)) {
            SerializationUtil::addNameValueIfNotEmpty(
                'context',
                $this->context->jsonSerialize(),
                /* ref */ $result
            );
        }

        if (!is_null($this->exception)) {
            SerializationUtil::addNameValueIfNotEmpty(
                'exception',
                $this->exception->jsonSerialize(),
                /* ref */ $result
            );
        }

        return $result;
    }
}
