<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util\Deserialization;

use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Tests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class SpanDataDeserializer extends EventDataDeserializer
{
    use ExecutionSegmentDataDeserializerTrait;

    /** @var SpanData */
    private $result;

    private function __construct(SpanData $result)
    {
        $this->result = $result;
    }

    /**
     *
     * @param array<string, mixed> $deserializedRawData
     *
     * @return SpanData
     */
    public static function deserialize(array $deserializedRawData): SpanData
    {
        $result = new SpanData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidSpanData($result);
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

        return (new class extends SpanData {
            /**
             * @param string   $key
             * @param mixed    $value
             * @param SpanData $result
             *
             * @return bool
             */
            public static function deserializeKeyValueImpl(string $key, $value, SpanData $result): bool
            {
                switch ($key) {
                    case 'action':
                        $result->action = ValidationUtil::assertValidKeywordString($value);
                        return true;

                    case 'parent_id':
                        $result->parentId = ValidationUtil::assertValidExecutionSegmentId($value);
                        return true;

                    case 'start':
                        $result->start = ValidationUtil::assertValidSpanStart($value);
                        return true;

                    case 'subtype':
                        $result->subtype = ValidationUtil::assertValidKeywordString($value);
                        return true;

                    case 'transaction_id':
                        $result->transactionId = ValidationUtil::assertValidExecutionSegmentId($value);
                        return true;

                    default:
                        return false;
                }
            }
        })->deserializeKeyValueImpl($key, $value, $this->result);
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

        return (new class extends SpanData {
            /**
             * @param string   $key
             * @param mixed    $value
             * @param SpanData $result
             *
             * @return bool
             */
            public static function deserializeContextKeyValueImpl(string $key, $value, SpanData $result): bool
            {
                switch ($key) {
                    // case 'destination':
                    //     $result->destination = ValidationUtil::assertValid...($value);
                    //     return true;

                    default:
                        return false;
                }
            }
        })->deserializeContextKeyValueImpl($key, $value, $this->result);
    }
}
