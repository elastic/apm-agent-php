<?php

declare(strict_types=1);

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\ExecutionSegmentContextData;
use Elastic\Apm\Impl\TransactionContextData;
use Elastic\Apm\Impl\TransactionData;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TransactionDataDeserializer extends ExecutionSegmentDataDeserializer
{
    /** @var TransactionData */
    private $result;

    private function __construct(TransactionData $result)
    {
        parent::__construct($result);
        $this->result = $result;
    }

    private function lazyContextData(): TransactionContextData
    {
        if (is_null($this->result->context)) {
            $this->result->context = new TransactionContextData();
        }
        return $this->result->context;
    }

    protected function executionSegmentContextData(): ExecutionSegmentContextData
    {
        return $this->lazyContextData();
    }

    /**
     * @param array<string, mixed> $deserializedRawData
     *
     * @return TransactionData
     */
    public static function deserialize(array $deserializedRawData): TransactionData
    {
        $result = new TransactionData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidTransactionData($result);
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
        if (parent::deserializeKeyValue($key, $value)) {
            return true;
        }

        switch ($key) {
            case 'parent_id':
                $this->result->parentId = ValidationUtil::assertValidExecutionSegmentId($value);
                return true;

            case 'result':
                $this->result->result = ValidationUtil::assertValidKeywordString($value);
                return true;

            case 'span_count':
                $this->deserializeSpanCount($value);
                return true;

            case 'sampled':
                $this->result->isSampled = ValidationUtil::assertValidBool($value);
                return true;

            default:
                return false;
        }
    }

    /**
     * @param array<string, mixed> $deserializedRawData
     */
    public function deserializeSpanCount(array $deserializedRawData): void
    {
        foreach ($deserializedRawData as $key => $value) {
            switch ($key) {
                case 'dropped':
                    $this->result->droppedSpansCount
                        = ValidationUtil::assertValidTransactionDroppedSpansCount($value);
                    break;

                case 'started':
                    $this->result->startedSpansCount
                        = ValidationUtil::assertValidTransactionStartedSpansCount($value);
                    break;

                default:
                    throw DataDeserializer::buildException("Unknown key: span_count->`$key'");
            }
        }
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    public function deserializeContextKeyValue(string $key, $value): bool
    {
        if (parent::deserializeContextKeyValue($key, $value)) {
            return true;
        }

        switch ($key) {
            // case 'http':
            //     $this->lazyContextData()->http = ValidationUtil::assertValid...($value);
            //     return true;

            default:
                return false;
        }
    }
}
