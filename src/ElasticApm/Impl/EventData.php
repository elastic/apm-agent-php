<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Util\ObjectToStringUsingPropertiesTrait;
use Elastic\Apm\Impl\Util\TextUtil;
use JsonSerializable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class EventData implements JsonSerializable
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
            if ($this->shouldSerializeProperty($thisObjPropName, $thisObjPropValue)) {
                $this->serializeProperty($thisObjPropName, $thisObjPropValue, /* ref */ $result);
            }
        }

        return $result;
    }

    /**
     * @param string $propKey
     * @param mixed  $propValue
     *
     * @return bool
     */
    protected function shouldSerializeProperty(string $propKey, $propValue): bool
    {
        $shouldSerializeMethodName = 'shouldSerialize' . TextUtil::camelToPascalCase($propKey);
        if (method_exists($this, $shouldSerializeMethodName)) {
            return $this->$shouldSerializeMethodName();
        }

        if (is_null($propValue)) {
            return false;
        }

        if (is_object($propValue) && method_exists($propValue, 'jsonSerialize')) {
            return !empty($propValue->jsonSerialize());
        }

        return true;
    }

    /**
     * @param string               $propKey
     * @param mixed                $propValue
     * @param array<string, mixed> $result
     */
    protected function serializeProperty(string $propKey, $propValue, array &$result): void
    {
        $result[TextUtil::camelToSnakeCase($propKey)] = $propValue;
    }

    /**
     * @param mixed $data
     *
     * @return mixed
     */
    public static function convertToData($data)
    {
        if (get_class($data) === get_called_class()) {
            return $data;
        }

        $result = new static(); // @phpstan-ignore-line
        // @phpstan-ignore-next-line - see https://github.com/phpstan/phpstan/issues/1060
        foreach ($result as $propKey => $propValue) {
            $methodName = static::getterMethodNameForConvertToData($propKey);
            $result->$propKey = static::convertPropertyValueToData($data->$methodName());
        }
        return $result;
    }

    protected static function getterMethodNameForConvertToData(string $propKey): string
    {
        return $propKey;
    }

    /**
     * @param mixed $propValue
     *
     * @return mixed
     */
    protected static function convertPropertyValueToData($propValue)
    {
        return $propValue;
    }

    public function __toString(): string
    {
        return $this->toStringUsingProperties(['logger']);
    }
}
