<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\ServerComm;

use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\MetadataInterface;
use Elastic\Apm\SpanDataInterface;
use Elastic\Apm\TransactionDataInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class EventSender implements EventSinkInterface
{
    /** @var MetadataInterface */
    private $metadata;

    /** @var SpanDataInterface[] */
    private $spans = [];

    /** @inheritDoc */
    public function setMetadata(MetadataInterface $metadata): void
    {
        $this->metadata = $metadata;
    }

    /** @inheritDoc */
    public function consumeTransactionData(TransactionDataInterface $transactionData): void
    {
        $serializedMetadata = '{"metadata":';
        $serializedMetadata .= SerializationUtil::serializeMetadata($this->metadata);
        $serializedMetadata .= "}";

        $serializedEvents = $serializedMetadata;

        $serializedEvents .= "\n";
        $serializedEvents .= '{"transaction":';
        $serializedEvents .= SerializationUtil::serializeTransaction($transactionData);
        $serializedEvents .= "}";

        foreach ($this->spans as $span) {
            $serializedEvents .= "\n";
            $serializedEvents .= '{"span":';
            $serializedEvents .= SerializationUtil::serializeSpan($span);
            $serializedEvents .= '}';
        }

        if (extension_loaded('elasticapm')) {
            /**
             * elasticapm_* functions are provided by the elasticapm extension
             *
             * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
             * @phpstan-ignore-next-line
             */
            \elasticapm_send_to_server($serializedMetadata, $serializedEvents);
        }
    }

    /** @inheritDoc */
    public function consumeSpanData(SpanDataInterface $span): void
    {
        $this->spans[] = $span;
    }
}
