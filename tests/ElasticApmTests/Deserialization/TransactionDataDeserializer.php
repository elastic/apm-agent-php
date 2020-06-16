<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Deserialization;

use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Tests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TransactionDataDeserializer extends EventDataDeserializer
{
    use ExecutionSegmentDataDeserializerTrait;

    /** @var TransactionData */
    private $result;

    private function __construct(TransactionData $result)
    {
        $this->result = $result;
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
        if ($this->executionSegmentDeserializeKeyValue($key, $value)) {
            return true;
        }

        return (new class extends TransactionData {
            /**
             * @param string                      $key
             * @param mixed                       $value
             * @param TransactionData             $result
             * @param TransactionDataDeserializer $deserializer
             *
             * @return bool
             */
            public static function deserializeKeyValueImpl(
                string $key,
                $value,
                TransactionData $result,
                TransactionDataDeserializer $deserializer
            ): bool {
                switch ($key) {
                    case 'parent_id':
                        $result->parentId = ValidationUtil::assertValidExecutionSegmentId($value);
                        return true;

                    case 'span_count':
                        $deserializer->deserializeSpanCount($value);
                        return true;

                    default:
                        return false;
                }
            }
        })->deserializeKeyValueImpl($key, $value, $this->result, $this);
    }

    /**
     * @param array<string, mixed> $deserializedRawData
     */
    public function deserializeSpanCount(array $deserializedRawData): void
    {
        (new class extends TransactionData {
            /**
             * @param array<string, mixed> $deserializedRawData
             * @param TransactionData      $result
             */
            public static function deserializeSpanCountImpl(array $deserializedRawData, TransactionData $result): void
            {
                foreach ($deserializedRawData as $key => $value) {
                    switch ($key) {
                        case 'dropped':
                            $result->droppedSpansCount
                                = ValidationUtil::assertValidTransactionDroppedSpansCount($value);
                            break;

                        case 'started':
                            $result->startedSpansCount
                                = ValidationUtil::assertValidTransactionStartedSpansCount($value);
                            break;

                        default:
                            throw EventDataDeserializer::buildException("Unknown key: span_count->`$key'");
                    }
                }
            }
        })->deserializeSpanCountImpl($deserializedRawData, $this->result);
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    public function deserializeContextKeyValue(string $key, $value): bool
    {
        if ($this->executionSegmentDeserializeContextKeyValue($key, $value)) {
            return true;
        }

        return (new class extends TransactionData {
            /**
             * @param string          $key
             * @param mixed           $value
             * @param TransactionData $result
             *
             * @return bool
             */
            public static function deserializeContextKeyValueImpl(string $key, $value, TransactionData $result): bool
            {
                switch ($key) {
                    // case 'http':
                    //     $result->http = ValidationUtil::assertValid...($value);
                    //     return true;

                    default:
                        return false;
                }
            }
        })->deserializeContextKeyValueImpl($key, $value, $this->result);
    }
}
