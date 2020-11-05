<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggableToJsonEncodable
{
    use StaticClassTrait;

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public static function convert($value)
    {
        if (is_null($value)) {
            return null;
        }

        // Scalar variables are those containing an int, float, string or bool.
        // Types array, object and resource are not scalar.
        if (is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return self::convertArray($value);
        }

        if (is_resource($value)) {
            return self::convertOpenResource($value);
        }

        if (is_object($value)) {
            return self::convertObject($value);
        }

        return [LogConsts::TYPE_KEY => DbgUtil::getType($value), LogConsts::VALUE_AS_STRING_KEY => strval($value)];
    }

    /**
     * @param array<mixed, mixed> $array
     *
     * @return array<mixed, mixed>
     */
    private static function convertArray(array $array)
    {
        return self::convertArrayImpl($array, self::isListArray($array));
    }

    /**
     * @param array<mixed, mixed> $array
     *
     * @return bool
     */
    private static function isListArray(array $array): bool
    {
        $expectedKey = 0;
        foreach ($array as $key => $_) {
            if ($key !== $expectedKey++) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<mixed, mixed> $array
     * @param bool                $isListArray
     *
     * @return array<mixed, mixed>
     */
    private static function convertArrayImpl(array $array, bool $isListArray): array
    {
        $arrayCount = count($array);
        $smallArrayMaxCount = $isListArray
            ? LogConsts::SMALL_LIST_ARRAY_MAX_COUNT
            : LogConsts::SMALL_MAP_ARRAY_MAX_COUNT;
        if ($arrayCount <= $smallArrayMaxCount) {
            return self::convertSmallArray($array, $isListArray);
        }

        $result = [LogConsts::TYPE_KEY => LogConsts::LIST_ARRAY_TYPE_VALUE];
        $result[LogConsts::ARRAY_COUNT_KEY] = $arrayCount;

        $halfOfSmallArrayMaxCount = intdiv($smallArrayMaxCount, 2);
        $firstElements = array_slice($array, 0, $halfOfSmallArrayMaxCount);
        $result['0-' . intdiv($smallArrayMaxCount, 2)]
            = self::convertSmallArray($firstElements, $isListArray);

        $result[($arrayCount - $halfOfSmallArrayMaxCount) . '-' . $arrayCount]
            = self::convertSmallArray(array_slice($array, -$halfOfSmallArrayMaxCount), $isListArray);

        return $result;
    }

    /**
     * @param array<mixed, mixed> $array
     * @param bool                $isListArray
     *
     * @return array<mixed, mixed>
     */
    private static function convertSmallArray(array $array, bool $isListArray): array
    {
        return $isListArray ? self::convertSmallListArray($array) : self::convertSmallMapArray($array);
    }

    /**
     * @param array<mixed> $listArray
     *
     * @return array<mixed>
     */
    private static function convertSmallListArray(array $listArray): array
    {
        $result = [];
        foreach ($listArray as $value) {
            $result[] = self::convert($value);
        }
        return $result;
    }

    /**
     * @param array<mixed, mixed> $mapArrayValue
     *
     * @return array<mixed, mixed>
     */
    private static function convertSmallMapArray(array $mapArrayValue): array
    {
        return self::isStringKeysMapArray($mapArrayValue)
            ? self::convertSmallStringKeysMapArray($mapArrayValue)
            : self::convertSmallMixedKeysMapArray($mapArrayValue);
    }

    /**
     * @param array<mixed, mixed> $mapArrayValue
     *
     * @return bool
     */
    private static function isStringKeysMapArray(array $mapArrayValue): bool
    {
        foreach ($mapArrayValue as $key => $_) {
            if (!is_string($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<mixed, mixed> $mapArrayValue
     *
     * @return array<mixed, mixed>
     */
    private static function convertSmallStringKeysMapArray(array $mapArrayValue): array
    {
        $result = [];
        foreach ($mapArrayValue as $key => $value) {
            $result[$key] = self::convert($value);
        }
        return $result;
    }

    /**
     * @param array<mixed, mixed> $mapArrayValue
     *
     * @return array<mixed, mixed>
     */
    private static function convertSmallMixedKeysMapArray(array $mapArrayValue): array
    {
        $result = [];
        foreach ($mapArrayValue as $key => $value) {
            $result[] = [self::convert($key), self::convert($value)];
        }
        return $result;
    }

    /**
     * @param resource $resource
     *
     * @return mixed
     */
    private static function convertOpenResource($resource)
    {
        return [
            LogConsts::TYPE_KEY          => LogConsts::RESOURCE_TYPE_VALUE,
            LogConsts::RESOURCE_TYPE_KEY => get_resource_type($resource),
            LogConsts::RESOURCE_ID_KEY   => intval($resource),
        ];
    }

    /**
     * @param object $object
     *
     * @return mixed
     */
    private static function convertObject(object $object)
    {
        if ($object instanceof LoggableInterface) {
            return self::convertLoggable($object);
        }

        if ($object instanceof Throwable) {
            return self::convertThrowable($object);
        }

        if (method_exists($object, '__debugInfo')) {
            return [
                LogConsts::TYPE_KEY                => get_class($object),
                LogConsts::VALUE_AS_DEBUG_INFO_KEY => self::convert($object->__debugInfo()),
            ];
        }

        if (method_exists($object, '__toString')) {
            return [
                LogConsts::TYPE_KEY            => get_class($object),
                LogConsts::VALUE_AS_STRING_KEY => self::convert($object->__toString()),
            ];
        }

        return [
            LogConsts::TYPE_KEY        => get_class($object),
            LogConsts::OBJECT_ID_KEY   => spl_object_id($object),
            LogConsts::OBJECT_HASH_KEY => spl_object_hash($object),
        ];
    }

    /**
     * @param LoggableInterface $loggable
     *
     * @return mixed
     */
    private static function convertLoggable(LoggableInterface $loggable)
    {
        $logStream = new LogStream();
        $loggable->toLog($logStream);
        return self::convert($logStream->value);
    }

    /**
     * @param Throwable $throwable
     *
     * @return mixed
     */
    private static function convertThrowable(Throwable $throwable)
    {
        return [
            LogConsts::TYPE_KEY            => get_class($throwable),
            LogConsts::VALUE_AS_STRING_KEY => self::convert($throwable->__toString()),
        ];
    }
}
