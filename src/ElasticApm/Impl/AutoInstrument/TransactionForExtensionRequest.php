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

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\HttpDistributedTracing;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\UrlUtil;
use Elastic\Apm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TransactionForExtensionRequest
{
    private const DEFAULT_NAME = 'Unnamed transaction';

    /** @var Tracer */
    private $tracer;

    /** @var Logger */
    private $logger;

    /** @var TransactionInterface */
    private $transactionForRequest;

    public function __construct(Tracer $tracer, float $requestInitStartTime)
    {
        $this->tracer = $tracer;
        $this->logger = $tracer->loggerFactory()
                               ->loggerForClass(LogCategory::AUTO_INSTRUMENTATION, __NAMESPACE__, __CLASS__, __FILE__);

        $this->transactionForRequest = $this->beginTransaction($requestInitStartTime);
    }

    private function beginTransaction(float $requestInitStartTime): TransactionInterface
    {
        $name = self::isCliScript() ? $this->discoverCliName() : $this->discoverHttpName();
        $type = self::isCliScript() ? Constants::TRANSACTION_TYPE_CLI : Constants::TRANSACTION_TYPE_REQUEST;
        $timestamp = $this->discoverTimestamp($requestInitStartTime);
        $distributedTracingData = $this->discoverIncomingDistributedTracingData();

        return $this->tracer->beginCurrentTransaction($name, $type, $timestamp, $distributedTracingData);
    }

    public function onShutdown(): void
    {
        if ($this->transactionForRequest->isNoop() || $this->transactionForRequest->hasEnded()) {
            return;
        }

        if (is_null($this->transactionForRequest->getResult()) && !self::isCliScript()) {
            $discoveredResult = $this->discoverHttpResult();
            if (!is_null($discoveredResult)) {
                $this->transactionForRequest->setResult($discoveredResult);
            }
        }

        $this->transactionForRequest->end();
    }

    private static function isCliScript(): bool
    {
        return PHP_SAPI === 'cli';
    }

    private function discoverCliName(): string
    {
        global $argc, $argv;
        if (isset($argc) && ($argc > 0) && isset($argv) && !empty($argv[0])) {
            $cliScriptName = basename($argv[0]);
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Successfully discovered CLI script name - using it for transaction name',
                ['cliScriptName' => $cliScriptName]
            );
            return $cliScriptName;
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Could not discover CLI script name - using default transaction name',
            ['DEFAULT_NAME' => self::DEFAULT_NAME]
        );
        return self::DEFAULT_NAME;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    private function getServerVarElement(string $key)
    {
        /**
         * Sometimes $_SERVER is defined. It seems related to auto_globals_jit
         * but it's not easily reproducible even with auto_globals_jit=On
         * See also https://bugs.php.net/bug.php?id=69081
         *
         * Disable PHPStan complaining:
         *      Variable $_SERVER in isset() always exists and is not nullable.
         *
         * @phpstan-ignore-next-line
         */
        if (!isset($_SERVER)) {
            ($loggerProxy = $this->logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('$_SERVER variable is not set');
            return null;
        }

        return ArrayUtil::getValueIfKeyExistsElse($key, $_SERVER, null);
    }

    private function discoverHttpName(): string
    {
        if (($requestUri = self::getServerVarElement('REQUEST_URI')) === null) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Could not discover HTTP data to derive transaction name - using default transaction name',
                ['DEFAULT_NAME' => self::DEFAULT_NAME]
            );
            return self::DEFAULT_NAME;
        }

        $urlPath = UrlUtil::extractPathPart($requestUri);
        if ($urlPath === null) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Failed to extract path part from request URL - using default transaction name',
                ['requestUri' => $requestUri, 'DEFAULT_NAME' => self::DEFAULT_NAME]
            );
            return self::DEFAULT_NAME;
        }

        $requestMethod = self::getServerVarElement('REQUEST_METHOD');
        $name = ($requestMethod === null) ? $urlPath : ($requestMethod . ' ' . $urlPath);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Successfully discovered HTTP data to derive transaction name', ['name' => $name]);

        return $name;
    }

    private function discoverTimestamp(float $requestInitStartTime): float
    {
        $serverRequestTimeAsString = self::getServerVarElement('REQUEST_TIME_FLOAT');
        if ($serverRequestTimeAsString === null) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Using requestInitStartTime for transaction serverRequestTimeInMicroseconds',
                ['requestInitStartTime' => $requestInitStartTime]
            );
            return $requestInitStartTime;
        }

        $serverRequestTimeInSeconds = floatval($serverRequestTimeAsString);
        $serverRequestTimeInMicroseconds = $serverRequestTimeInSeconds * 1000000;

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Using $_SERVER[\'REQUEST_TIME_FLOAT\'] for transaction serverRequestTimeInMicroseconds',
            ['serverRequestTimeInMicroseconds' => $serverRequestTimeInMicroseconds]
        );

        return $serverRequestTimeInMicroseconds;
    }

    private function discoverIncomingDistributedTracingData(): ?string
    {
        $headerName = HttpDistributedTracing::TRACE_PARENT_HEADER_NAME;
        $traceParentHeaderKey = 'HTTP_' . strtoupper($headerName);

        $traceParentHeaderValue = self::getServerVarElement($traceParentHeaderKey);
        if ($traceParentHeaderValue === null) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Incoming ' . $headerName . ' HTTP request header not found');
            return null;
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Incoming ' . HttpDistributedTracing::TRACE_PARENT_HEADER_NAME . ' HTTP request header found',
            ['traceParentHeaderValue' => $traceParentHeaderValue]
        );

        return $traceParentHeaderValue;
    }

    private function discoverHttpResult(): ?string
    {
        $statusCode = http_response_code();
        if (!is_int($statusCode)) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'http_response_code() returned a value that is not an int',
                ['statusCode' => $statusCode]
            );
            return null;
        }

        $statusCode100s = intdiv($statusCode, 100);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Discovered result for HTTP transaction',
            ['statusCode' => $statusCode, '$statusCode100s' => $statusCode100s]
        );

        return 'HTTP ' . $statusCode100s . 'xx';
    }
}
