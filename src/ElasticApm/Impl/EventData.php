<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Util\TextUtil;
use JsonSerializable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class EventData implements JsonSerializable
{
    /**
     * @return array<string, mixed>
     *
     * Called by json_encode
     * @noinspection PhpUnused
     */
    public function jsonSerialize(): array
    {
        $result = [];

        // Until this bug https://github.com/phpstan/phpstan/issues/1060 is fixed
        // @phpstan-ignore-next-line
        foreach ($this as $propKey => $propValue) {
            if ($this->shouldSerializeProperty($propKey, $propValue)) {
                $this->serializeProperty($propKey, $propValue, /* ref */ $result);
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
        // Until this bug https://github.com/phpstan/phpstan/issues/1060 is fixed
        // @phpstan-ignore-next-line
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
}
