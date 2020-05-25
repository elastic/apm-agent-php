<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util\Deserialization;

use Elastic\Apm\Impl\ServerComm\SerializationException;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Tests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class SerializationTestUtil
{
    use StaticClassTrait;

    /**
     * @param mixed $decodedJson
     *
     * @return string
     */
    public static function prettyEncodeJson($decodedJson): string
    {
        $prettyFormatted = json_encode($decodedJson, JSON_PRETTY_PRINT);
        if ($prettyFormatted === false) {
            throw new SerializationException(
                'Serialization failed'
                . '. json_last_error_msg(): ' . json_last_error_msg()
                . '. $decodedJson: ' . $decodedJson
            );
        }
        return $prettyFormatted;
    }

    /**
     * @param string $inputJson
     *
     * @return string
     */
    public static function prettyFormatJson(string $inputJson): string
    {
        return self::prettyEncodeJson(self::deserializeJson($inputJson, /* asAssocArray */ true));
    }

    /**
     * @param string $serializedData
     * @param bool   $asAssocArray
     *
     * @return mixed
     */
    public static function deserializeJson(string $serializedData, bool $asAssocArray)
    {
        $deserializedRawData = json_decode($serializedData, /* assoc: */ $asAssocArray);
        if (is_null($deserializedRawData)) {
            throw ValidationUtil::buildException(
                'Deserialization failed'
                . '. json_last_error_msg(): ' . json_last_error_msg()
                . '. deserializedRawData: ' . $deserializedRawData
            );
        }
        return $deserializedRawData;
    }
}
