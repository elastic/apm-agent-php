<?php

declare(strict_types=1);

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\Metadata;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class MetadataDeserializer extends DataDeserializer
{
    /** @var Metadata */
    private $result;

    private function __construct(Metadata $result)
    {
        $this->result = $result;
    }

    /**
     *
     * @param array<string, mixed> $deserializedRawData
     *
     * @return Metadata
     */
    public static function deserialize(array $deserializedRawData): Metadata
    {
        $result = new Metadata();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidMetadata($result);
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
            case 'process':
                $this->result->process = ProcessDataDeserializer::deserialize($value);
                return true;

            case 'service':
                $this->result->service = ServiceDataDeserializer::deserialize($value);
                return true;

            default:
                return false;
        }
    }
}
