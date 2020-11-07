<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\BackendComm;

use Elastic\Apm\Impl\Config\Snapshot as ConfigSnapshot;
use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\TransactionData;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class EventSender implements EventSinkInterface
{
    /** @var Logger */
    private $logger;

    /** @var ConfigSnapshot */
    private $config;

    public function __construct(ConfigSnapshot $config, LoggerFactory $loggerFactory)
    {
        $this->config = $config;
        $this->logger = $loggerFactory->loggerForClass(LogCategory::BACKEND_COMM, __NAMESPACE__, __CLASS__, __FILE__);
        $this->logger->addContext('this', $this);
    }

    /** @inheritDoc */
    public function consume(Metadata $metadata, array $spansData, ?TransactionData $transactionData): void
    {
        $serializedMetadata = '{"metadata":';
        $serializedMetadata .= SerializationUtil::serializeAsJson($metadata);
        $serializedMetadata .= "}";

        $serializedEvents = $serializedMetadata;

        foreach ($spansData as $span) {
            $serializedEvents .= "\n";
            $serializedEvents .= '{"span":';
            $serializedEvents .= SerializationUtil::serializeAsJson($span);
            $serializedEvents .= '}';
        }

        if (!is_null($transactionData)) {
            $serializedEvents .= "\n";
            $serializedEvents .= '{"transaction":';
            $serializedEvents .= SerializationUtil::serializeAsJson($transactionData);
            $serializedEvents .= "}";
        }

        /** @noinspection SpellCheckingInspection */
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Calling elastic_apm_send_to_server...',
            [
                'serverTimeout'               => $this->config->serverTimeout(),
                'strlen($serializedMetadata)' => strlen($serializedMetadata),
                'strlen($serializedEvents)'   => strlen($serializedEvents),
            ]
        );

        /**
         * elastic_apm_* functions are provided by the elastic_apm extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         * @phpstan-ignore-next-line
         */
        \elastic_apm_send_to_server($this->config->serverTimeout(), $serializedMetadata, $serializedEvents);
    }
}
