<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util\Deserialization;

use Elastic\Apm\Impl\ServiceNodeData;
use Elastic\Apm\Tests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ServiceNodeDataDeserializer extends EventDataDeserializer
{
    /** @var ServiceNodeData */
    private $result;

    private function __construct(ServiceNodeData $result)
    {
        $this->result = $result;
    }

    /**
     *
     * @param array<string, mixed> $deserializedRawData
     *
     * @return ServiceNodeData
     */
    public static function deserialize(array $deserializedRawData): ServiceNodeData
    {
        $result = new ServiceNodeData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidServiceNodeData($result);
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
        return (new class extends ServiceNodeData {
            /**
             * @param string      $key
             * @param mixed       $value
             * @param ServiceNodeData $result
             *
             * @return bool
             */
            public static function deserializeKeyValueImpl(string $key, $value, ServiceNodeData $result): bool
            {
                switch ($key) {
                    case 'configured_name':
                        $result->configuredName = ValidationUtil::assertValidKeywordString($value);
                        return true;

                    default:
                        return false;
                }
            }
        })->deserializeKeyValueImpl($key, $value, $this->result);
    }
}
