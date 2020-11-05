<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\BackendComm;

use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\MetadataInterface;
use Elastic\Apm\Impl\Span;
use Elastic\Apm\Impl\Transaction;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\JsonUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\SpanDataInterface;
use Elastic\Apm\TransactionDataInterface;
use Exception;

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

    public static function serializeMetadata(MetadataInterface $data): string
    {
        return self::serializeAsJson(Metadata::convertToData($data));
    }

    public static function serializeTransaction(TransactionDataInterface $data): string
    {
        return self::serializeAsJson(Transaction::convertToData($data));
    }

    public static function serializeSpan(SpanDataInterface $data): string
    {
        return self::serializeAsJson(Span::convertToData($data));
    }
}
