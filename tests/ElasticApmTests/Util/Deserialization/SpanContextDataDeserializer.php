<?php

declare(strict_types=1);

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\SpanContextData;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class SpanContextDataDeserializer extends ExecutionSegmentContextDataDeserializer
{
    /** @var SpanContextData */
    private $result;

    private function __construct(SpanContextData $result)
    {
        parent::__construct($result);
        $this->result = $result;
    }

    /**
     *
     * @param array<string, mixed> $deserializedRawData
     *
     * @return SpanContextData
     */
    public static function deserialize(array $deserializedRawData): SpanContextData
    {
        $result = new SpanContextData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidSpanContextData($result);
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
            // case 'destination':
            //     $this->lazyContextData()->destination = ValidationUtil::assertValid...($value);
            //     return true;

            default:
                return false;
        }
    }
}
