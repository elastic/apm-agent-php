<?php

declare(strict_types=1);

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\ErrorTransactionData;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ErrorTransactionDataDeserializer extends DataDeserializer
{
    /** @var ErrorTransactionData */
    private $result;

    private function __construct(ErrorTransactionData $result)
    {
        $this->result = $result;
    }

    /**
     *
     * @param array<string, mixed> $deserializedRawData
     *
     * @return ErrorTransactionData
     */
    public static function deserialize(array $deserializedRawData): ErrorTransactionData
    {
        $result = new ErrorTransactionData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidErrorTransactionData($result);
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
            case 'sampled':
                $this->result->isSampled = ValidationUtil::assertValidBool($value);
                return true;

            case 'type':
                $this->result->type = ValidationUtil::assertValidKeywordString($value);
                return true;

            default:
                return false;
        }
    }
}
