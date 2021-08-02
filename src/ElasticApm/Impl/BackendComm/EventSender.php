<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

namespace Elastic\Apm\Impl\BackendComm;

use Elastic\Apm\Impl\BreakdownMetrics\PerTransaction as BreakdownMetricsPerTransaction;
use Elastic\Apm\Impl\Config\Snapshot as ConfigSnapshot;
use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\MetricSetData;
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
    public function consume(
        Metadata $metadata,
        array $spansData,
        array $errorsData,
        ?BreakdownMetricsPerTransaction $breakdownMetricsPerTransaction,
        ?TransactionData $transactionData
    ): void {
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

        foreach ($errorsData as $error) {
            $serializedEvents .= "\n";
            $serializedEvents .= '{"error":';
            $serializedEvents .= SerializationUtil::serializeAsJson($error);
            $serializedEvents .= '}';
        }

        if ($breakdownMetricsPerTransaction !== null) {
            $breakdownMetricsPerTransaction->forEachMetricSet(
                function (MetricSetData $metricSet) use (&$serializedEvents) {
                    $serializedEvents .= "\n";
                    $serializedEvents .= '{"metricset":';
                    $serializedEvents .= SerializationUtil::serializeAsJson($metricSet);
                    $serializedEvents .= '}';
                }
            );
        }

        if ($transactionData !== null) {
            $serializedEvents .= "\n";
            $serializedEvents .= '{"transaction":';
            $serializedEvents .= SerializationUtil::serializeAsJson($transactionData);
            $serializedEvents .= "}";
        }

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
