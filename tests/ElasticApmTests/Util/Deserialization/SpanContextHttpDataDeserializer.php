<?php

declare(strict_types=1);

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\SpanContextHttpData;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class SpanContextHttpDataDeserializer extends DataDeserializer
{
    /** @var SpanContextHttpData */
    private $result;

    private function __construct(SpanContextHttpData $result)
    {
        $this->result = $result;
    }

    /**
     *
     * @param array<string, mixed> $deserializedRawData
     *
     * @return SpanContextHttpData
     */
    public static function deserialize(array $deserializedRawData): SpanContextHttpData
    {
        $result = new SpanContextHttpData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidSpanContextHttpData($result);
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
            case 'url':
                $this->result->url = ValidationUtil::assertValidNullableNonKeywordString($value);
                return true;

            case 'status_code':
                $this->result->statusCode = ValidationUtil::assertValidNullableHttpStatusCode($value);
                return true;

            case 'method':
                $this->result->method = ValidationUtil::assertValidNullableKeywordString($value);
                return true;

            default:
                return false;
        }
    }
}
