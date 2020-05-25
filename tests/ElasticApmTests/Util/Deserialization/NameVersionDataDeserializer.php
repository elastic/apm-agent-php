<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util\Deserialization;

use Elastic\Apm\Impl\NameVersionData;
use Elastic\Apm\Tests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class NameVersionDataDeserializer extends EventDataDeserializer
{
    /** @var NameVersionData */
    private $result;

    private function __construct(NameVersionData $result)
    {
        $this->result = $result;
    }

    /**
     *
     * @param array<string, mixed> $deserializedRawData
     *
     * @return NameVersionData
     */
    public static function deserialize(array $deserializedRawData): NameVersionData
    {
        $result = new NameVersionData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidNameVersionData($result);
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
        return (new class extends NameVersionData {
            /**
             * @param string          $key
             * @param mixed           $value
             * @param NameVersionData $result
             *
             * @return bool
             */
            public static function deserializeKeyValueImpl(string $key, $value, NameVersionData $result): bool
            {
                switch ($key) {
                    case 'name':
                        $result->name = ValidationUtil::assertValidKeywordString($value);
                        return true;

                    case 'version':
                        $result->version = ValidationUtil::assertValidKeywordString($value);
                        return true;

                    default:
                        return false;
                }
            }
        })->deserializeKeyValueImpl($key, $value, $this->result);
    }
}
