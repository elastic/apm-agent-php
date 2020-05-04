<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Deserialization;

use Elastic\Apm\Impl\ProcessData;
use Elastic\Apm\Tests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ProcessDataDeserializer extends EventDataDeserializer
{
    /** @var ProcessData */
    private $result;

    private function __construct(ProcessData $result)
    {
        $this->result = $result;
    }

    /**
     *
     * @param array<string, mixed> $deserializedRawData
     *
     * @return ProcessData
     */
    public static function deserialize(array $deserializedRawData): ProcessData
    {
        $result = new ProcessData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidProcessData($result);
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
        return (new class extends ProcessData {
            /**
             * @param string      $key
             * @param mixed       $value
             * @param ProcessData $result
             *
             * @return bool
             */
            public static function deserializeKeyValueImpl(string $key, $value, ProcessData $result): bool
            {
                switch ($key) {
                    case 'pid':
                        $result->pid = ValidationUtil::assertValidProcessId($value);
                        return true;

                    default:
                        return false;
                }
            }
        })->deserializeKeyValueImpl($key, $value, $this->result);
    }
}
