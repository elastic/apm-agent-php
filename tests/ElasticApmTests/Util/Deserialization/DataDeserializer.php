<?php

declare(strict_types=1);

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\Util\ExceptionUtil;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class DataDeserializer
{
    /**
     *
     * @param array<string, mixed>  $deserializedRawData
     */
    protected function doDeserialize(array $deserializedRawData): void
    {
        foreach ($deserializedRawData as $key => $value) {
            if (!$this->deserializeKeyValue($key, $value)) {
                throw self::buildException("Unknown key: `$key'");
            }
        }
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    abstract protected function deserializeKeyValue(string $key, $value): bool;

    public static function buildException(
        ?string $msgDetails = null,
        int $code = 0,
        Throwable $previous = null
    ): DeserializationException {
        $msgStart = 'Deserialization failed';
        if (!is_null($msgDetails)) {
            $msgStart .= ': ';
            $msgStart .= $msgDetails;
        }

        return new DeserializationException(
            ExceptionUtil::buildMessage($msgStart, /* context: */ [], /* numberOfStackFramesToSkip */ 1),
            $code,
            $previous
        );
    }
}
