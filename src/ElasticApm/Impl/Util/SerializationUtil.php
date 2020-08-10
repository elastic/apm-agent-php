<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

use Elastic\Apm\Impl\ServerComm\SerializationException;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class SerializationUtil
{
    use StaticClassTrait;

    /**
     * @param array<string, mixed> $resultToProcess
     *
     * @return array<string, mixed>
     */
    public static function buildJsonSerializeResult(array $resultToProcess): array
    {
        return array_filter(
            $resultToProcess,
            function ($value) {
                return !is_null($value);
            }
        );
    }

    /**
     * @param array<string, mixed> $baseResult
     * @param array<string, mixed> $resultToProcess
     *
     * @return array<string, mixed>
     */
    public static function buildJsonSerializeResultWithBase(array $baseResult, array $resultToProcess): array
    {
        ($assertProxy = Assert::ifOnLevelEnabled())
        && $assertProxy->that(empty(array_intersect_key($baseResult, $resultToProcess)))
        && $assertProxy->info(
            'empty(array_intersect_key($baseResult, $resultToProcess)',
            ['$baseResult' => $baseResult, '$resultToProcess' => $resultToProcess]
        );

        return array_merge($baseResult, self::buildJsonSerializeResult($resultToProcess));
    }

    /**
     * @param mixed $objToCheck
     *
     * @return object|null
     */
    public static function nullIfEmpty($objToCheck)
    {
        if (is_null($objToCheck)) {
            return null;
        }

        return $objToCheck->isEmpty() ? null : $objToCheck;
    }

    /**
     * @param mixed  $obj
     * @param string $dbgObjDesc
     *
     * @return string
     */
    public static function serializeAsJson($obj, ?string $dbgObjDesc = null): string
    {
        $serializedData = json_encode($obj);
        if ($serializedData === false) {
            throw new SerializationException(
                'Serialization failed'
                . '. json_last_error_msg(): ' . json_last_error_msg()
                . '. $data: ' . ($dbgObjDesc ?? strval($obj))
            );
        }
        return $serializedData;
    }
}
