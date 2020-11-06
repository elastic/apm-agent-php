<?php

declare(strict_types=1);

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\ExecutionSegmentContextData;
use Elastic\Apm\Impl\SpanContextData;
use Elastic\Apm\Impl\SpanData;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class SpanDataDeserializer extends ExecutionSegmentDataDeserializer
{
    /** @var SpanData */
    private $result;

    private function __construct(SpanData $result)
    {
        parent::__construct($result);
        $this->result = $result;
    }

    private function lazyContextData(): SpanContextData
    {
        if (is_null($this->result->context)) {
            $this->result->context = new SpanContextData();
        }
        return $this->result->context;
    }

    protected function executionSegmentContextData(): ExecutionSegmentContextData
    {
        return $this->lazyContextData();
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
        if (parent::deserializeKeyValue($key, $value)) {
            return true;
        }

        switch ($key) {
            case 'action':
                $this->result->action = ValidationUtil::assertValidKeywordString($value);
                return true;

            case 'parent_id':
                $this->result->parentId = ValidationUtil::assertValidExecutionSegmentId($value);
                return true;

            case 'stacktrace':
                $this->result->stacktrace = StacktraceDeserializer::deserialize($value);
                return true;

            case 'subtype':
                $this->result->subtype = ValidationUtil::assertValidKeywordString($value);
                return true;

            case 'transaction_id':
                $this->result->transactionId = ValidationUtil::assertValidExecutionSegmentId($value);
                return true;

            default:
                return false;
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
            // case 'destination':
            //     $this->lazyContextData()->destination = ValidationUtil::assertValid...($value);
            //     return true;

            default:
                return false;
        }
    }
}
