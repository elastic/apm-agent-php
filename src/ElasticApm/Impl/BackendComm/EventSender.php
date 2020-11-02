<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\BackendComm;

use Elastic\Apm\Impl\Config\Snapshot as ConfigSnapshot;
use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\MetadataInterface;
use Elastic\Apm\TransactionDataInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class EventSender implements EventSinkInterface
{
    /** @var Logger */
    private $logger;

    /** @var MetadataInterface */
    private $metadata;

    /** @var ConfigSnapshot */
    private $config;

    public function __construct(ConfigSnapshot $config, LoggerFactory $loggerFactory)
    {
        $this->config = $config;
        $this->logger = $loggerFactory->loggerForClass(LogCategory::BACKEND_COMM, __NAMESPACE__, __CLASS__, __FILE__);
        $this->logger->addContext('this', $this);
    }

    public function setMetadata(MetadataInterface $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function consume(array $spans, ?TransactionDataInterface $transaction): void
    {
        $serializedMetadata = '{"metadata":';
        $serializedMetadata .= SerializationUtil::serializeMetadata($this->metadata);
        $serializedMetadata .= "}";

        $serializedEvents = $serializedMetadata;

        foreach ($spans as $span) {
            $serializedEvents .= "\n";
            $serializedEvents .= '{"span":';
            $serializedEvents .= SerializationUtil::serializeSpan($span);
            $serializedEvents .= '}';
        }

        if (!is_null($transaction)) {
            $serializedEvents .= "\n";
            $serializedEvents .= '{"transaction":';
            $serializedEvents .= SerializationUtil::serializeTransaction($transaction);
            $serializedEvents .= "}";
        }

        if (extension_loaded('elastic_apm')) {
            /**
             * elastic_apm_* functions are provided by the elastic_apm extension
             *
             * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
             * @phpstan-ignore-next-line
             */
            \elastic_apm_send_to_server($this->config->serverTimeout(), $serializedMetadata, $serializedEvents);
        }
    }
}
