<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\Impl\Util\ObjectToStringUsingPropertiesTrait;
use JsonSerializable;

abstract class SharedDataBase implements JsonSerializable
{
    use ObjectToStringUsingPropertiesTrait;

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
     * @return SharedDataBase
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
