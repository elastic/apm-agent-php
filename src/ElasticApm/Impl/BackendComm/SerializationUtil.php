<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\BackendComm;

use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\JsonUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Exception;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class SerializationUtil
{
    use StaticClassTrait;

    /** @var bool */
    public static $isInTestingContext = false;

    /**
     * @param mixed $data
     *
     * @return string
     */
    public static function serializeAsJson($data): string
    {
        try {
            $serializedData = JsonUtil::encode($data);
        } catch (Exception $ex) {
            throw new SerializationException(
                ExceptionUtil::buildMessage('Serialization failed', ['data' => $data]),
                $ex
            );
        }
        return $serializedData;
    }

    /**
     * @param string               $name
     * @param mixed                $value
     * @param array<string, mixed> $nameToValue
     *
     * @return void
     */
    public static function addNameValueIfNotNull(string $name, $value, array &$nameToValue): void
    {
        if (self::$isInTestingContext && array_key_exists($name, $nameToValue)) {
            throw new SerializationException(
                ExceptionUtil::buildMessage(
                    'Given key already exists in given array',
                    ['name' => $name, 'value' => $value, 'nameToValue' => $nameToValue]
                )
            );
        }

        if (is_null($value)) {
            return;
        }

        $nameToValue[$name] = $value;
    }
}
