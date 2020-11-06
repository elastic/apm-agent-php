<?php

declare(strict_types=1);

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\ServiceData;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ServiceDataDeserializer extends DataDeserializer
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
        switch ($key) {
            case 'name':
                $this->result->name = ValidationUtil::assertValidServiceName($value);
                return true;

            case 'version':
                $this->result->version = ValidationUtil::assertValidKeywordString($value);
                return true;

            case 'environment':
                $this->result->environment = ValidationUtil::assertValidKeywordString($value);
                return true;

            case 'agent':
                $this->result->agent = NameVersionDataDeserializer::deserialize($value);
                return true;

            case 'framework':
                $this->result->framework = NameVersionDataDeserializer::deserialize($value);
                return true;

            case 'language':
                $this->result->language = NameVersionDataDeserializer::deserialize($value);
                return true;

            case 'runtime':
                $this->result->runtime = NameVersionDataDeserializer::deserialize($value);
                return true;

            default:
                return false;
        }
    }
}
