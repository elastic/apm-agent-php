<?php

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use JsonSerializable;

abstract class SharedData implements JsonSerializable, LoggableInterface
{
    use LoggableTrait;

    /**
     * @return array<string, mixed>
     *
     * Called by json_encode
     * @noinspection PhpUnused
     */
    public function jsonSerialize(): array
    {
        $result = [];

        // @phpstan-ignore-next-line - see https://github.com/phpstan/phpstan/issues/1060
        foreach ($this as $thisObjPropName => $thisObjPropValue) {
            $result[$thisObjPropName] = $thisObjPropValue;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $decodedJson
     *
     * @return SharedData
     *
     * @phpstan-return static
     */
    public static function deserializeFromJson(array $decodedJson): self
    {
        $result = new static(); // @phpstan-ignore-line

        foreach ($decodedJson as $jsonKey => $jsonVal) {
            $result->$jsonKey = $jsonVal;
        }

        return $result;
    }
}
