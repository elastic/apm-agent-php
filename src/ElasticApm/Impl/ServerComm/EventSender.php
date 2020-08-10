<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\ServerComm;

use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\Util\SerializationUtil;
use Elastic\Apm\Metadata;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class EventSender implements EventSinkInterface
{
    /** @var Metadata */
    private $metadata;

    /** @var SpanInterface[] */
    private $spans = [];

    public function setMetadata(Metadata $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function consumeTransaction(TransactionInterface $transaction): void
    {
        $serializedMetadata = '{"metadata":';
        $serializedMetadata .= SerializationUtil::serializeAsJson($this->metadata);
        $serializedMetadata .= "}";

        $serializedEvents = $serializedMetadata;

        $serializedEvents .= "\n";
        $serializedEvents .= '{"transaction":';
        $serializedEvents .= SerializationUtil::serializeAsJson($transaction);
        $serializedEvents .= "}";

        foreach ($this->spans as $span) {
            $serializedEvents .= "\n";
            $serializedEvents .= '{"span":';
            $serializedEvents .= SerializationUtil::serializeAsJson($span);
            $serializedEvents .= '}';
        }

        if (extension_loaded('elastic_apm')) {
            /**
             * elastic_apm_* functions are provided by the elastic_apm extension
             *
             * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
             * @phpstan-ignore-next-line
             */
            \elastic_apm_send_to_server($serializedMetadata, $serializedEvents);
        }
    }

    public function consumeSpan(SpanInterface $span): void
    {
        $this->spans[] = $span;
    }
}
