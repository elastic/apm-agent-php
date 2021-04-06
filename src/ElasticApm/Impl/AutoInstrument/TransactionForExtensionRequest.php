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
use Elastic\Apm\Impl\Util\UrlParts;
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

    /** @var ?string */
    private $httpMethod = null;

    /** @var ?string */
    private $fullUrl = null;

    /** @var ?UrlParts */
    private $urlParts = null;

    /** @var TransactionInterface */
    private $transactionForRequest;

    public function __construct(Tracer $tracer, float $requestInitStartTime)
    {
        $this->tracer = $tracer;
        $this->logger = $tracer->loggerFactory()
                               ->loggerForClass(LogCategory::AUTO_INSTRUMENTATION, __NAMESPACE__, __CLASS__, __FILE__);

        $this->transactionForRequest = $this->beginTransaction($requestInitStartTime);
        if (!self::isCliScript()) {
            $this->setTxPropsBasedOnHttpRequestData();
        }
    }

    private function beginTransaction(float $requestInitStartTime): TransactionInterface
    {
        $this->discoverHttpRequestData();
        $name = self::isCliScript() ? $this->discoverCliName() : $this->discoverHttpName();
        $type = self::isCliScript() ? Constants::TRANSACTION_TYPE_CLI : Constants::TRANSACTION_TYPE_REQUEST;
        $timestamp = $this->discoverTimestamp($requestInitStartTime);
        $distributedTracingData = $this->discoverIncomingDistributedTracingData();

        return $this->tracer->beginCurrentTransaction($name, $type, $timestamp, $distributedTracingData);
    }

    private static function isGlobalServerVarSet(): bool
    {
        /**
         * Sometimes $_SERVER is not set. It seems related to auto_globals_jit
         * but it's not easily reproducible even with auto_globals_jit=On
         * See also https://bugs.php.net/bug.php?id=69081
         *
         * Disable PHPStan complaining:
         *      Variable $_SERVER in isset() always exists and is not nullable.
         *
         * @phpstan-ignore-next-line
         */
        return isset($_SERVER) && !empty($_SERVER);
    }

    private function discoverHttpRequestData(): void
    {
        if (!self::isGlobalServerVarSet()) {
            ($loggerProxy = $this->logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('$_SERVER variable is not populated - forcing PHP engine to populate it...');

            /**
             * elastic_apm_* functions are provided by the elastic_apm extension
             *
             * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
             * @phpstan-ignore-next-line
             */
            \elastic_apm_force_init_server_global_var();

            if (!self::isGlobalServerVarSet()) {
                ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    '$_SERVER variable is not populated even after forcing PHP engine to populate it'
                    . ' - agent will have to fallback on defaults'
                );
                return;
            }
        }

        $this->httpMethod = self::getMandatoryServerVarElement('REQUEST_METHOD');

        $this->urlParts = new UrlParts();

        $serverHttps = self::getOptionalServerVarElement('HTTPS');
        $this->urlParts->scheme = $serverHttps !== null && !empty($serverHttps) ? 'https' : 'http';

        $hostPort = self::getMandatoryServerVarElement('HTTP_HOST');
        if ($hostPort !== null) {
            UrlUtil::splitHostPort($hostPort, /* ref */ $this->urlParts->host, /* ref */ $this->urlParts->port);
            if ($this->urlParts->host === null) {
                ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Failed to extract host part from $_SERVER["HTTP_HOST"]',
                    ['$_SERVER["HTTP_HOST"]' => $hostPort]
                );
            }
        }

        $pathQuery = self::getMandatoryServerVarElement('REQUEST_URI');
        if ($pathQuery !== null) {
            UrlUtil::splitPathQuery(
                $pathQuery,
                /* ref */ $this->urlParts->path,
                /* ref */ $this->urlParts->query
            );
            if ($this->urlParts->path === null) {
                ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Failed to extract path part from $_SERVER["REQUEST_URI"]',
                    ['$_SERVER["REQUEST_URI"]' => $pathQuery]
                );
            }
        }

        $queryString = self::getOptionalServerVarElement('QUERY_STRING');
        if ($queryString !== null && is_string($queryString)) {
            $this->urlParts->query = $queryString;
        }

        $this->fullUrl = self::buildFullUrl($this->urlParts->scheme, $hostPort, $pathQuery);
    }

    private static function buildFullUrl(?string $scheme, ?string $hostPort, ?string $pathQuery): ?string
    {
        if ($hostPort === null) {
            return null;
        }

        $fullUrl = '';

        if ($scheme !== null) {
            $fullUrl .= $scheme . '://';
        }

        $fullUrl .= $hostPort;

        if ($pathQuery !== null) {
            $fullUrl .= $pathQuery;
        }

        return $fullUrl;
    }

    private function setTxPropsBasedOnHttpRequestData(): void
    {
        if ($this->httpMethod !== null) {
            $this->transactionForRequest->context()->request()->setMethod($this->httpMethod);
        }

        if ($this->urlParts !== null) {
            if ($this->urlParts->host !== null) {
                $this->transactionForRequest->context()->request()->url()->setDomain($this->urlParts->host);
            }
            if ($this->urlParts->path !== null) {
                $this->transactionForRequest->context()->request()->url()->setPath($this->urlParts->path);
            }
            if ($this->urlParts->port !== null) {
                $this->transactionForRequest->context()->request()->url()->setPort($this->urlParts->port);
            }
            if ($this->urlParts->scheme !== null) {
                $this->transactionForRequest->context()->request()->url()->setProtocol($this->urlParts->scheme);
            }
            if ($this->urlParts->query !== null) {
                $this->transactionForRequest->context()->request()->url()->setQuery($this->urlParts->query);
            }
        }

        if ($this->fullUrl !== null) {
            $this->transactionForRequest->context()->request()->url()->setFull($this->fullUrl);
            $this->transactionForRequest->context()->request()->url()->setOriginal($this->fullUrl);
        }
    }

    private function beforeHttpEnd(): void
    {
        if ($this->transactionForRequest->getResult() === null) {
            $discoveredResult = $this->discoverHttpResult();
            if ($discoveredResult !== null) {
                $this->transactionForRequest->setResult($discoveredResult);
            }
        }
    }

    public function onShutdown(): void
    {
        if ($this->transactionForRequest->isNoop() || $this->transactionForRequest->hasEnded()) {
            return;
        }

        if (!self::isCliScript()) {
            $this->beforeHttpEnd();
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
    private function getOptionalServerVarElement(string $key)
    {
        return isset($_SERVER[$key]) ? $_SERVER[$key] : null;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    private function getMandatoryServerVarElement(string $key)
    {
        $val = $this->getOptionalServerVarElement($key);
        if ($val === null) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('$_SERVER does not contain `' . $key . '\' key');
            return null;
        }

        return $_SERVER[$key];
    }

    private function discoverHttpName(): string
    {
        if ($this->urlParts === null || $this->urlParts->path === null) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Failed to  discover path part of URL to derive transaction name - using default transaction name',
                ['DEFAULT_NAME' => self::DEFAULT_NAME]
            );
            return self::DEFAULT_NAME;
        }

        $name = ($this->httpMethod === null)
            ? $this->urlParts->path
            : ($this->httpMethod . ' ' . $this->urlParts->path);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Successfully discovered HTTP data to derive transaction name', ['name' => $name]);

        return $name;
    }

    private function discoverTimestamp(float $requestInitStartTime): float
    {
        $serverRequestTimeAsString = self::getMandatoryServerVarElement('REQUEST_TIME_FLOAT');
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

        $traceParentHeaderValue = self::getOptionalServerVarElement($traceParentHeaderKey);
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
