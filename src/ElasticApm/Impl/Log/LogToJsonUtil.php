<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LogToJsonUtil
{
    use StaticClassTrait;

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public static function convert($value)
    {
        try {
            return self::convertImpl($value);
        } catch (Throwable $throwable) {
            return [
                'logSubSystemInternalMessage' => 'FAILED to convert value',
                'type' => DbgUtil::getType($value),
                'throwable' => self::convertThrowable($throwable, /* $includeValuesInStackTrace */ false)
            ];
        }
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private static function convertImpl($value)
    {
        if (is_null($value) || is_bool($value) || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return ArrayUtil::isList($value) ? self::convertList($value) : self::convertMap($value);
        }

        if (is_object($value)) {
            return self::convertObject($value);
        }

        return '<' . DbgUtil::getType($value) . '> ' . strval($value);
    }

    /**
     * @param mixed $value
     * @return string
     */
    public static function toString($value): string
    {
        $jsonEncodeResult = json_encode(self::convert($value));
        if ($jsonEncodeResult === false) {
            return 'json_encode() failed. json_last_error_msg(): ' . json_last_error_msg();
        }
        return $jsonEncodeResult;
    }

    /**
     * @param array<mixed> $list
     *
     * @return array<mixed>
     */
    private static function convertList(array $list): array
    {
        return array_map(
        /**
         * @param mixed $value
         *
         * @return mixed
         */
            function ($value) {
                return self::convert($value);
            },
            $list
        );
    }

    /**
     * @param array<mixed> $map
     *
     * @return array<string, mixed>
     */
    private static function convertMap(array $map): array
    {
        $result = [];
        foreach ($map as $key => $value) {
            $result[strval($key)] = self::convert($value);
        }
        return $result;
    }

    /**
     * @param object $object
     *
     * @return mixed
     */
    private static function convertObject(object $object)
    {
        if ($object instanceof LoggableInterface) {
            return new LoggableToJsonSerializableWrapper($object);
        }

        if ($object instanceof Throwable) {
            return self::convertThrowable($object);
        }

        if (method_exists($object, '__debugInfo')) {
            return self::convert($object->__debugInfo());
        }

        if (method_exists($object, '__toString')) {
            return $object->__toString();
        }

        return ['kind' => 'object', 'class' => DbgUtil::getType($object)];
    }

    /**
     * @param Throwable $throwable
     * @param bool      $includeValuesInStackTrace
     *
     * @return array<string, mixed>
     */
    private static function convertThrowable(Throwable $throwable, bool $includeValuesInStackTrace = true): array
    {
        return [
            'class' => get_class($throwable),
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'stackTrace' => new LoggableStackTrace(
                $throwable->getTrace(),
                /* $numberOfStackFramesToSkip */ 0,
                $includeValuesInStackTrace
            ),
            'previous' => $throwable->getPrevious()
        ];
    }
}
