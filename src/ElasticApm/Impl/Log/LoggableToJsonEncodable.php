<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Impl\Util\TextUtil;
use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggableToJsonEncodable
{
    use StaticClassTrait;

    private const MAX_DEPTH = 10;

    private const IS_DTO_OBJECT_CACHE_MAX_COUNT_LOW_WATER_MARK = 10000;
    private const IS_DTO_OBJECT_CACHE_MAX_COUNT_HIGH_WATER_MARK
        = 2 * self::IS_DTO_OBJECT_CACHE_MAX_COUNT_LOW_WATER_MARK;

    /** @var array<string, bool> */
    private static $isDtoObjectCache = [];

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public static function convert($value, int $depth)
    {
        if ($value === null) {
            return null;
        }

        // Scalar variables are those containing an int, float, string or bool.
        // Types array, object and resource are not scalar.
        if (is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            if ($depth >= self::MAX_DEPTH) {
                return [
                    LogConsts::MAX_DEPTH_REACHED => $depth,
                    LogConsts::TYPE_KEY          => DbgUtil::getType($value),
                    LogConsts::ARRAY_COUNT_KEY   => count($value),
                ];
            }
            return self::convertArray($value, $depth + 1);
        }

        if (is_resource($value)) {
            return self::convertOpenResource($value);
        }

        if (is_object($value)) {
            if ($depth >= self::MAX_DEPTH) {
                return [
                    LogConsts::MAX_DEPTH_REACHED => $depth,
                    LogConsts::TYPE_KEY          => DbgUtil::getType($value),
                ];
            }
            return self::convertObject($value, $depth + 1);
        }

        return [LogConsts::TYPE_KEY => DbgUtil::getType($value), LogConsts::VALUE_AS_STRING_KEY => strval($value)];
    }

    /**
     * @param array<mixed, mixed> $array
     *
     * @return array<mixed, mixed>
     */
    private static function convertArray(array $array, int $depth): array
    {
        return self::convertArrayImpl($array, ArrayUtil::isList($array), $depth);
    }

    /**
     * @param array<mixed, mixed> $array
     * @param bool                $isListArray
     * @param int                 $depth
     *
     * @return array<mixed, mixed>
     */
    private static function convertArrayImpl(array $array, bool $isListArray, int $depth): array
    {
        $arrayCount = count($array);
        $smallArrayMaxCount = $isListArray
            ? LogConsts::SMALL_LIST_ARRAY_MAX_COUNT
            : LogConsts::SMALL_MAP_ARRAY_MAX_COUNT;
        if ($arrayCount <= $smallArrayMaxCount) {
            return self::convertSmallArray($array, $isListArray, $depth);
        }

        $result = [LogConsts::TYPE_KEY => LogConsts::LIST_ARRAY_TYPE_VALUE];
        $result[LogConsts::ARRAY_COUNT_KEY] = $arrayCount;

        $halfOfSmallArrayMaxCount = intdiv($smallArrayMaxCount, 2);
        $firstElements = array_slice($array, 0, $halfOfSmallArrayMaxCount);
        $result['0-' . intdiv($smallArrayMaxCount, 2)]
            = self::convertSmallArray($firstElements, $isListArray, $depth);

        $result[($arrayCount - $halfOfSmallArrayMaxCount) . '-' . $arrayCount]
            = self::convertSmallArray(array_slice($array, -$halfOfSmallArrayMaxCount), $isListArray, $depth);

        return $result;
    }

    /**
     * @param array<mixed, mixed> $array
     * @param bool                $isListArray
     * @param int                 $depth
     *
     * @return array<mixed, mixed>
     */
    private static function convertSmallArray(array $array, bool $isListArray, int $depth): array
    {
        return $isListArray ? self::convertSmallListArray($array, $depth) : self::convertSmallMapArray($array, $depth);
    }

    /**
     * @param array<mixed> $listArray
     *
     * @return array<mixed>
     */
    private static function convertSmallListArray(array $listArray, int $depth): array
    {
        $result = [];
        foreach ($listArray as $value) {
            $result[] = self::convert($value, $depth);
        }
        return $result;
    }

    /**
     * @param array<mixed, mixed> $mapArrayValue
     *
     * @return array<mixed, mixed>
     */
    private static function convertSmallMapArray(array $mapArrayValue, int $depth): array
    {
        return self::isStringKeysMapArray($mapArrayValue)
            ? self::convertSmallStringKeysMapArray($mapArrayValue, $depth)
            : self::convertSmallMixedKeysMapArray($mapArrayValue, $depth);
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
    private static function convertSmallStringKeysMapArray(array $mapArrayValue, int $depth): array
    {
        $result = [];
        foreach ($mapArrayValue as $key => $value) {
            $result[$key] = self::convert($value, $depth);
        }
        return $result;
    }

    /**
     * @param array<mixed, mixed> $mapArrayValue
     * @param int                 $depth
     *
     * @return array<mixed, mixed>
     */
    private static function convertSmallMixedKeysMapArray(array $mapArrayValue, int $depth): array
    {
        $result = [];
        foreach ($mapArrayValue as $key => $value) {
            $result[] = [self::convert($key, $depth), self::convert($value, $depth)];
        }
        return $result;
    }

    /**
     * @param resource $resource
     *
     * @return array<string, mixed>
     */
    private static function convertOpenResource($resource): array
    {
        return [
            LogConsts::TYPE_KEY          => LogConsts::RESOURCE_TYPE_VALUE,
            LogConsts::RESOURCE_TYPE_KEY => get_resource_type($resource),
            LogConsts::RESOURCE_ID_KEY   => intval($resource),
        ];
    }

    /**
     * @param object $object
     * @param int    $depth
     *
     * @return mixed
     */
    private static function convertObject(object $object, int $depth)
    {
        if ($object instanceof LoggableInterface) {
            return self::convertLoggable($object, $depth);
        }

        if ($object instanceof Throwable) {
            return self::convertThrowable($object, $depth);
        }

        $fqClassName = get_class($object);
        $isFromElasticNamespace = TextUtil::isPrefixOf('Elastic\\Apm\\', $fqClassName)
                                  || TextUtil::isPrefixOf('ElasticApmTests\\', $fqClassName);
        if ($isFromElasticNamespace && self::isDtoObject($object)) {
            return self::convertDtoObject($object, $depth);
        }

        if (method_exists($object, '__debugInfo')) {
            return [
                LogConsts::TYPE_KEY                => get_class($object),
                LogConsts::VALUE_AS_DEBUG_INFO_KEY => self::convert($object->__debugInfo(), $depth),
            ];
        }

        if (method_exists($object, '__toString')) {
            return [
                LogConsts::TYPE_KEY            => get_class($object),
                LogConsts::VALUE_AS_STRING_KEY => self::convert($object->__toString(), $depth),
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
     * @param int               $depth
     *
     * @return mixed
     */
    private static function convertLoggable(LoggableInterface $loggable, int $depth)
    {
        $logStream = new LogStream();
        $loggable->toLog($logStream);
        return self::convert($logStream->value, $depth);
    }

    /**
     * @param Throwable $throwable
     * @param int       $depth
     *
     * @return array<string, mixed>
     */
    private static function convertThrowable(Throwable $throwable, int $depth): array
    {
        return [
            LogConsts::TYPE_KEY            => get_class($throwable),
            LogConsts::VALUE_AS_STRING_KEY => self::convert($throwable->__toString(), $depth),
        ];
    }

    /**
     * @param object $object
     * @param int    $depth
     *
     * @return string|array<string, mixed>
     * @phpstan-return array<string, mixed>
     */
    private static function convertDtoObject(object $object, int $depth)
    {
        $class = get_class($object);
        try {
            $currentClass = new ReflectionClass($class);
            /** @phpstan-ignore-next-line */
        } catch (ReflectionException $ex) {
            return LoggingSubsystem::onInternalFailure('Failed to reflect', ['class' => $class], $ex);
        }

        $nameToValue = [];
        while (true) {
            foreach ($currentClass->getProperties() as $reflectionProperty) {
                if ($reflectionProperty->isStatic()) {
                    continue;
                }

                $propName = $reflectionProperty->name;
                $propValue = $reflectionProperty->getValue($object);
                $nameToValue[$propName] = self::convert($propValue, $depth);
            }
            $currentClass = $currentClass->getParentClass();
            if ($currentClass === false) {
                break;
            }
        }
        return $nameToValue;
    }

    private static function isDtoObject(object $object): bool
    {
        $class = get_class($object);
        $valueInCache = ArrayUtil::getValueIfKeyExistsElse($class, self::$isDtoObjectCache, null);
        if ($valueInCache !== null) {
            return $valueInCache;
        }

        $value = self::detectIfDtoObject($class);

        self::addToIsDtoObjectCache($class, $value);

        return $value;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return bool
     */
    private static function detectIfDtoObject(string $className): bool
    {
        try {
            $currentClass = new ReflectionClass($className);
            /** @phpstan-ignore-next-line */
        } catch (ReflectionException $ex) {
            LoggingSubsystem::onInternalFailure('Failed to reflect', ['className' => $className], $ex);
            return false;
        }

        while (true) {
            foreach ($currentClass->getProperties() as $reflectionProperty) {
                if ($reflectionProperty->isStatic()) {
                    continue;
                }

                if (!$reflectionProperty->isPublic()) {
                    return false;
                }
            }
            $currentClass = $currentClass->getParentClass();
            if ($currentClass === false) {
                break;
            }
        }

        return true;
    }

    private static function addToIsDtoObjectCache(string $class, bool $value): void
    {
        $isDtoObjectCacheCount = count(self::$isDtoObjectCache);
        if ($isDtoObjectCacheCount >= self::IS_DTO_OBJECT_CACHE_MAX_COUNT_HIGH_WATER_MARK) {
            self::$isDtoObjectCache = array_slice(
                self::$isDtoObjectCache,
                $isDtoObjectCacheCount - self::IS_DTO_OBJECT_CACHE_MAX_COUNT_LOW_WATER_MARK
            );
        }

        self::$isDtoObjectCache[$class] = $value;
    }
}
