<?php

declare(strict_types=1);

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\ErrorData;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ErrorDataDeserializer extends DataDeserializer
{
    /** @var ErrorData */
    private $result;

    private function __construct(ErrorData $result)
    {
        $this->result = $result;
    }

    /**
     *
     * @param array<string, mixed> $deserializedRawData
     *
     * @return ErrorData
     */
    public static function deserialize(array $deserializedRawData): ErrorData
    {
        $result = new ErrorData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidErrorData($result);
        return $result;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    protected function deserializeKeyValue(string $key, $value): bool
    {
        switch ($key) {
            // public $transaction = null;

            case 'timestamp':
                $this->result->timestamp = ValidationUtil::assertValidTimestamp($value);
                return true;

            case 'id':
                $this->result->id = ValidationUtil::assertValidErrorId($value);
                return true;

            case 'trace_id':
                $this->result->traceId = ValidationUtil::assertValidTraceId($value);
                return true;

            case 'transaction_id':
                $this->result->transactionId = ValidationUtil::assertValidExecutionSegmentId($value);
                return true;

            case 'parent_id':
                $this->result->parentId = ValidationUtil::assertValidExecutionSegmentId($value);
                return true;

            case 'transaction':
                $this->result->transaction = ErrorTransactionDataDeserializer::deserialize($value);
                return true;

            case 'context':
                $this->result->context = TransactionContextDataDeserializer::deserialize($value);
                return true;

            case 'exception':
                $this->result->exception = ErrorExceptionDataDeserializer::deserialize($value);
                return true;

            default:
                return false;
        }
    }
}
