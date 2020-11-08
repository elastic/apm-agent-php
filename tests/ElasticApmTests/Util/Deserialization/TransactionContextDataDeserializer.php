<?php

declare(strict_types=1);

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\TransactionContextData;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TransactionContextDataDeserializer extends ExecutionSegmentContextDataDeserializer
{
    /** @var TransactionContextData */
    private $result;

    private function __construct(TransactionContextData $result)
    {
        parent::__construct($result);
        $this->result = $result;
    }

    /**
     *
     * @param array<string, mixed> $deserializedRawData
     *
     * @return TransactionContextData
     */
    public static function deserialize(array $deserializedRawData): TransactionContextData
    {
        $result = new TransactionContextData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidTransactionContextData($result);
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
            // case 'http':
            //     $this->lazyContextData()->http = ValidationUtil::assertValid...($value);
            //     return true;

            default:
                return false;
        }
    }
}
