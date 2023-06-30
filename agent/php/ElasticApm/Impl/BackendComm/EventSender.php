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

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\BreakdownMetrics\PerTransaction as BreakdownMetricsPerTransaction;
use Elastic\Apm\Impl\Config\DevInternalSubOptionNames;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Config\Snapshot as ConfigSnapshot;
use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\MetricSet;
use Elastic\Apm\Impl\Transaction;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class EventSender implements EventSinkInterface
{
    public const AGENT_GITHUB_REPO_NAME = 'apm-agent-php';

    /** @var Logger */
    private $logger;

    /** @var ConfigSnapshot */
    private $config;

    /** @var ?string */
    private $userAgentHttpHeader = null;

    public function __construct(ConfigSnapshot $config, LoggerFactory $loggerFactory)
    {
        $this->config = $config;
        $this->logger = $loggerFactory->loggerForClass(LogCategory::BACKEND_COMM, __NAMESPACE__, __CLASS__, __FILE__);
        $this->logger->addContext('this', $this);
    }

    public static function buildUserAgentHttpHeader(string $serviceName, ?string $serviceVersion): string
    {
        // https://github.com/elastic/apm/blob/main/specs/agents/transport.md#user-agent
        // Header value should start with agent github repository as prefix and version:
        // apm-agent-${language}/${agent.version}.
        // If both service.name and service.version are set, append (${service.name} ${service.version})
        // If only service.name is set, append (${service.name})
        //
        // Examples:
        //      apm-agent-java/v1.25.0
        //      apm-agent-ruby/4.4.0 (my_service)
        //      apm-agent-python/6.4.0 (my_service v42.7)

        $headerValue = self::AGENT_GITHUB_REPO_NAME . '/' . ElasticApm::VERSION;

        $serviceNameVersionSuffix = $serviceName;
        // Escape characters not allowed in User-Agent. Taken from
        // https://github.com/elastic/apm-agent-nodejs/blob/52c8f27a379b2f1914c41aaef7e54ac2cb4c92f8/lib/config.js#L844
        $serviceNameVersionSuffix .= ($serviceVersion === null)
            ? ''
            : (' ' . preg_replace('/[^\t \x21-\x27\x2a-\x5b\x5d-\x7e\x80-\xff]/', '_', $serviceVersion));

        $headerValue .= ' (' . $serviceNameVersionSuffix . ')';
        return $headerValue;
    }

    /** @inheritDoc */
    public function consume(
        Metadata $metadata,
        array $spans,
        array $errors,
        ?BreakdownMetricsPerTransaction $breakdownMetricsPerTransaction,
        ?Transaction $transaction
    ): void {
        if ($this->userAgentHttpHeader === null) {
            $this->userAgentHttpHeader = self::buildUserAgentHttpHeader(
                $metadata->service->name,
                $metadata->service->version
            );
        }

        $serializedMetadata = '{"metadata":';
        $serializedMetadata .= SerializationUtil::serializeAsJson($metadata);
        $serializedMetadata .= "}";

        $serializedEvents = $serializedMetadata;

        foreach ($spans as $span) {
            $serializedEvents .= "\n";
            $serializedEvents .= '{"span":';
            $serializedEvents .= SerializationUtil::serializeAsJson($span);
            $serializedEvents .= '}';
        }

        foreach ($errors as $error) {
            $serializedEvents .= "\n";
            $serializedEvents .= '{"error":';
            $serializedEvents .= SerializationUtil::serializeAsJson($error);
            $serializedEvents .= '}';
        }

        if ($breakdownMetricsPerTransaction !== null) {
            $breakdownMetricsPerTransaction->forEachMetricSet(
                function (MetricSet $metricSet) use (&$serializedEvents) {
                    $serializedEvents .= "\n";
                    $serializedEvents .= '{"metricset":';
                    $serializedEvents .= SerializationUtil::serializeAsJson($metricSet);
                    $serializedEvents .= '}';
                }
            );
        }

        if ($transaction !== null) {
            $serializedEvents .= "\n";
            $serializedEvents .= '{"transaction":';
            $serializedEvents .= SerializationUtil::serializeAsJson($transaction);
            $serializedEvents .= "}";
        }

        if ($this->config->devInternal()->dropEventsBeforeSendCCode()) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Dropping events because '
                . OptionNames::DEV_INTERNAL . ' sub-option ' . DevInternalSubOptionNames::DROP_EVENTS_BEFORE_SEND_C_CODE
                . ' is set'
            );
        } else {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Calling elastic_apm_send_to_server...',
                [
                    'userAgentHttpHeader'      => $this->userAgentHttpHeader,
                    'strlen(serializedEvents)' => strlen($serializedEvents),
                ]
            );

            /**
             * elastic_apm_* functions are provided by the elastic_apm extension
             *
             * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
             * @phpstan-ignore-next-line
             */
            \elastic_apm_send_to_server($this->userAgentHttpHeader, $serializedEvents);
        }
    }
}
