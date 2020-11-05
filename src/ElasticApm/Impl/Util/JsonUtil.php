<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class JsonUtil
{
    use StaticClassTrait;

    /**
     * @param mixed $data
     * @param bool  $prettyPrint
     *
     * @return string
     *
     * @throws JsonException
     */
    public static function encode($data, bool $prettyPrint = false): string
    {
        $options = 0;
        $options |= $prettyPrint ? JSON_PRETTY_PRINT : 0;
        $encodedData = json_encode($data, $options);
        if ($encodedData === false) {
            throw new JsonException(
                'json_encode() failed'
                . '. json_last_error_msg(): ' . json_last_error_msg()
                . '. dataType: ' . DbgUtil::getType($data)
            );
        }
        return $encodedData;
    }

    /**
     * @param string $encodedData
     * @param bool   $asAssocArray
     *
     * @return mixed
     */
    public static function decode(string $encodedData, bool $asAssocArray)
    {
        $decodedData = json_decode($encodedData, /* assoc: */ $asAssocArray);
        if (is_null($decodedData) && ($encodedData !== 'null')) {
            throw new JsonException(
                'json_decode() failed.'
                . ' json_last_error_msg(): ' . json_last_error_msg() . '.'
                . ' encodedData: ' . $encodedData . '.'
            );
        }
        return $decodedData;
    }
}
