<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Deserialization;

use Elastic\Apm\Impl\ServiceData;
use Elastic\Apm\Tests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ServiceDataDeserializer extends EventDataDeserializer
{
    /** @var ServiceData */
    private $result;

    private function __construct(ServiceData $result)
    {
        $this->result = $result;
    }

    /**
     *
     * @param array<string, mixed> $deserializedRawData
     *
     * @return ServiceData
     */
    public static function deserialize(array $deserializedRawData): ServiceData
    {
        $result = new ServiceData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidServiceData($result);
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
        return (new class extends ServiceData {
            /**
             * @param string      $key
             * @param mixed       $value
             * @param ServiceData $result
             *
             * @return bool
             */
            public static function deserializeKeyValueImpl(string $key, $value, ServiceData $result): bool
            {
                switch ($key) {
                    case 'name':
                        $result->name = ValidationUtil::assertValidServiceName($value);
                        return true;

                    case 'version':
                        $result->version = ValidationUtil::assertValidKeywordString($value);
                        return true;

                    case 'environment':
                        $result->environment = ValidationUtil::assertValidKeywordString($value);
                        return true;

                    case 'agent':
                        $result->agent = NameVersionDataDeserializer::deserialize($value);
                        return true;

                    case 'framework':
                        $result->framework = NameVersionDataDeserializer::deserialize($value);
                        return true;

                    case 'language':
                        $result->language = NameVersionDataDeserializer::deserialize($value);
                        return true;

                    case 'runtime':
                        $result->runtime = NameVersionDataDeserializer::deserialize($value);
                        return true;

                    default:
                        return false;
                }
            }
        })->deserializeKeyValueImpl($key, $value, $this->result);
    }
}
