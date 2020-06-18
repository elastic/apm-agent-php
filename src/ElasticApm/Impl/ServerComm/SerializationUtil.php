<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\ServerComm;

use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\MetadataInterface;
use Elastic\Apm\Impl\Span;
use Elastic\Apm\Impl\Transaction;
use Elastic\Apm\Impl\Util\Assert;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\SpanDataInterface;
use Elastic\Apm\TransactionDataInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class SerializationUtil
{
    use StaticClassTrait;

    /**
     * @param mixed  $data
     * @param string $dbgDataAsString
     *
     * @return string
     */
    public static function serializeAsJson($data, string $dbgDataAsString): string
    {
        $serializedData = json_encode($data);
        if ($serializedData === false) {
            throw new SerializationException(
                'Serialization failed'
                . '. json_last_error_msg(): ' . json_last_error_msg()
                . '. $data: ' . $dbgDataAsString
            );
        }
        return $serializedData;
    }

    public static function serializeMetadata(MetadataInterface $data): string
    {
        return self::serializeAsJson(Metadata::convertToData($data), Metadata::dataToString($data, get_class($data)));
    }

    public static function serializeTransaction(TransactionDataInterface $data): string
    {
        return self::serializeAsJson(
            Transaction::convertToData($data),
            Transaction::dataToString($data, get_class($data))
        );
    }

    public static function serializeSpan(SpanDataInterface $data): string
    {
        return self::serializeAsJson(Span::convertToData($data), Span::dataToString($data, get_class($data)));
    }
}
