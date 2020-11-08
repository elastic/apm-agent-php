<?php

declare(strict_types=1);

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\ErrorExceptionData;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ErrorExceptionDataDeserializer extends DataDeserializer
{
    /** @var ErrorExceptionData */
    private $result;

    private function __construct(ErrorExceptionData $result)
    {
        $this->result = $result;
    }

    /**
     *
     * @param array<string, mixed> $deserializedRawData
     *
     * @return ErrorExceptionData
     */
    public static function deserialize(array $deserializedRawData): ErrorExceptionData
    {
        $result = new ErrorExceptionData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidErrorExceptionData($result);
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
            case 'code':
                $this->result->code = ValidationUtil::assertValidErrorExceptionCode($value);
                return true;

            case 'message':
                $this->result->message = ValidationUtil::assertValidNullableNonKeywordString($value);
                return true;

            case 'module':
                $this->result->module = ValidationUtil::assertValidNullableKeywordString($value);
                return true;

            case 'stacktrace':
                $this->result->stacktrace = StacktraceDeserializer::deserialize($value);
                return true;

            case 'type':
                $this->result->type = ValidationUtil::assertValidNullableKeywordString($value);
                return true;

            default:
                return false;
        }
    }
}
