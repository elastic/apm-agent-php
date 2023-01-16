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

use Elastic\Apm\Impl\Config\DevInternalSubOptionNames;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Config\Snapshot as ConfigSnapshot;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\HttpDistributedTracing;
use Elastic\Apm\Impl\InferredSpansManager;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Span;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Transaction;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use Elastic\Apm\Impl\Util\TimeUtil;
use Elastic\Apm\Impl\Util\UrlParts;
use Elastic\Apm\Impl\Util\UrlUtil;
use Elastic\Apm\Impl\Util\WildcardListMatcher;
use Elastic\Apm\TransactionInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TransactionForExtensionRequest
{
    private const DEFAULT_NAME = 'Unnamed transaction';

    private const LARAVEL_ARTISAN_COMMAND_SCRIPT = 'artisan';

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

    /** @var ?TransactionInterface */
    private $transactionForRequest;

    /** @var ?Throwable  */
    private $lastThrown = null;

    /** @var ?InferredSpansManager  */
    private $inferredSpansManager = null;

    public function __construct(Tracer $tracer, float $requestInitStartTime)
    {
        $this->tracer = $tracer;
        $this->logger = $tracer->loggerFactory()
                               ->loggerForClass(LogCategory::AUTO_INSTRUMENTATION, __NAMESPACE__, __CLASS__, __FILE__)
                               ->addContext('this', $this);

        $this->transactionForRequest = $this->beginTransaction($requestInitStartTime);
        if ($this->transactionForRequest instanceof Transaction && $this->transactionForRequest->isSampled()) {
            $this->inferredSpansManager = new InferredSpansManager($tracer);
        }

        $this->tracer->onNewCurrentTransactionHasBegun->add(
            function (Transaction $transaction): void {
                PhpPartFacade::ensureHaveLatestDataDeferredByExtension();
                $transaction->onAboutToEnd->add(
                    function (Transaction $ignored): void {
                        PhpPartFacade::ensureHaveLatestDataDeferredByExtension();
                    }
                );
                $transaction->onCurrentSpanChanged->add(
                    function (?Span $span): void {
                        PhpPartFacade::ensureHaveLatestDataDeferredByExtension();
                        if ($span !== null) {
                            $span->onAboutToEnd->add(
                                function (Span $ignored): void {
                                    PhpPartFacade::ensureHaveLatestDataDeferredByExtension();
                                }
                            );
                        }
                    }
                );
            }
        );
    }

    public function getConfig(): ConfigSnapshot
    {
        return $this->tracer->getConfig();
    }

    private function beginTransaction(float $requestInitStartTime): ?TransactionInterface
    {
        if (!self::isCliScript()) {
            if (!$this->discoverHttpRequestData()) {
                return null;
            }
        }
        $name = self::isCliScript() ? $this->discoverCliName() : $this->discoverHttpName();
        $type = self::isCliScript() ? Constants::TRANSACTION_TYPE_CLI : Constants::TRANSACTION_TYPE_REQUEST;
        $timestamp = $this->discoverStartTime($requestInitStartTime);
        $distributedTracingHeaders = $this->getDistributedTracingHeaders();
        $distributedTracingHeaderExtractor = function (string $headerName) use ($distributedTracingHeaders): ?string {
            return ArrayUtil::getValueIfKeyExistsElse($headerName, $distributedTracingHeaders, null);
        };
        $tx = $this->tracer->newTransaction($name, $type)
                           ->asCurrent()
                           ->timestamp($timestamp)
                           ->distributedTracingHeaderExtractor($distributedTracingHeaderExtractor)
                           ->begin();
        if (!self::isCliScript() && !$tx->isNoop()) {
            $this->setTxPropsBasedOnHttpRequestData($tx);
        }

        return $tx;
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

    private function discoverHttpRequestData(): bool
    {
        /** @phpstan-ignore-next-line */
        if (!self::isGlobalServerVarSet()) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('$_SERVER variable is not populated - forcing PHP engine to populate it...');

            /**
             * elastic_apm_* functions are provided by the elastic_apm extension
             *
             * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
             * @phpstan-ignore-next-line
             */
            \elastic_apm_force_init_server_global_var();

            /** @phpstan-ignore-next-line */
            if (!self::isGlobalServerVarSet()) {
                ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    '$_SERVER variable is not populated even after forcing PHP engine to populate it'
                    . ' - agent will have to fallback on defaults'
                );
                return true;
            }
        }

        /** @var ?string */
        $urlPath = null;
        /** @var ?string */
        $urlQuery = null;

        $pathQuery = $this->getMandatoryServerVarStringElement('REQUEST_URI');
        if (is_string($pathQuery)) {
            UrlUtil::splitPathQuery($pathQuery, /* ref */ $urlPath, /* ref */ $urlQuery);
            if ($urlPath === null) {
                ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Failed to extract path part from $_SERVER["REQUEST_URI"]',
                    ['$_SERVER["REQUEST_URI"]' => $pathQuery]
                );
            } else {
                if ($this->shouldHttpTransactionBeIgnored($urlPath)) {
                    return false;
                }
            }
        }

        $this->httpMethod = $this->getMandatoryServerVarStringElement('REQUEST_METHOD');

        $this->urlParts = new UrlParts();
        $this->urlParts->path = $urlPath;
        $this->urlParts->query = $urlQuery;

        $serverHttps = self::getOptionalServerVarElement('HTTPS');
        $this->urlParts->scheme = !empty($serverHttps) ? 'https' : 'http';

        $hostPort = $this->getMandatoryServerVarStringElement('HTTP_HOST');
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

        $queryString = self::getOptionalServerVarElement('QUERY_STRING');
        if (is_string($queryString)) {
            $this->urlParts->query = $queryString;
        }

        $this->fullUrl = self::buildFullUrl($this->urlParts->scheme, $hostPort, $pathQuery);
        return true;
    }

    private function shouldHttpTransactionBeIgnored(string $urlPath): bool
    {
        $ignoreMatcher = $this->tracer->getConfig()->transactionIgnoreUrls();
        $matchedIgnoreExpr = WildcardListMatcher::matchNullable($ignoreMatcher, $urlPath);
        if ($matchedIgnoreExpr !== null) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Transaction is ignored because its URL path matched ' . OptionNames::TRANSACTION_IGNORE_URLS
                . ' configuration',
                [
                    'urlPath'                                               => $urlPath,
                    'matched ignore expression'                             => $matchedIgnoreExpr,
                    OptionNames::TRANSACTION_IGNORE_URLS . ' configuration' => $ignoreMatcher,
                ]
            );
            return true;
        }

        return false;
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

    private function setTxPropsBasedOnHttpRequestData(TransactionInterface $tx): void
    {
        if ($this->httpMethod !== null) {
            $tx->context()->request()->setMethod($this->httpMethod);
        }

        if ($this->urlParts !== null) {
            if ($this->urlParts->host !== null) {
                $tx->context()->request()->url()->setDomain($this->urlParts->host);
            }
            if ($this->urlParts->path !== null) {
                $tx->context()->request()->url()->setPath($this->urlParts->path);
            }
            if ($this->urlParts->port !== null) {
                $tx->context()->request()->url()->setPort($this->urlParts->port);
            }
            if ($this->urlParts->scheme !== null) {
                $tx->context()->request()->url()->setProtocol($this->urlParts->scheme);
            }
            if ($this->urlParts->query !== null) {
                $tx->context()->request()->url()->setQuery($this->urlParts->query);
            }
        }

        if ($this->fullUrl !== null) {
            $tx->context()->request()->url()->setFull($this->fullUrl);
            $tx->context()->request()->url()->setOriginal($this->fullUrl);
        }
    }

    private function beforeHttpEnd(TransactionInterface $tx): void
    {
        if ($tx->getResult() === null) {
            $this->discoverHttpResult($tx);
        }

        if ($tx->getOutcome() === null) {
            $this->discoverHttpOutcome($tx);
        }

        if ($tx->getOutcome() === Constants::OUTCOME_FAILURE && $this->lastThrown !== null) {
            $this->tracer->createErrorFromThrowable($this->lastThrown);
        }
    }

    private function logGcStatus(): void
    {
        if (!function_exists('gc_status')) {
            return;
        }

        /** @phpstan-ignore-next-line */
        $gcStatusRetVal = gc_status();

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Called gc_status()', ['gc_status() return value' => $gcStatusRetVal]);
    }

    public function onPhpError(PhpErrorData $phpErrorData): void
    {
        $relatedThrowable = null;
        if (
            $this->lastThrown !== null
            && $phpErrorData->message !== null
            && TextUtil::isPrefixOf('Uncaught Exception: ', $phpErrorData->message, /* isCaseSensitive: */ false)
        ) {
            $relatedThrowable = $this->lastThrown;
            $this->lastThrown = null;
        }
        $this->tracer->onPhpError($phpErrorData, $relatedThrowable);
    }

    /**
     * @param mixed $lastThrown
     *
     * @return void
     */
    public function setLastThrown($lastThrown): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered', ['lastThrown' => $lastThrown]);

        if (!($lastThrown instanceof Throwable)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'lastThrown is not an instance of Throwable - ignoring it...',
                ['lastThrown' => $lastThrown]
            );
            return;
        }

        $this->lastThrown = $lastThrown;
    }

    public function onShutdown(): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered');

        PhpPartFacade::ensureHaveLatestDataDeferredByExtension();

        if ($this->inferredSpansManager !== null) {
            $this->inferredSpansManager->shutdown();
        }

        $tx = $this->transactionForRequest;
        if ($tx === null || $tx->isNoop() || $tx->hasEnded()) {
            return;
        }

        if (!self::isCliScript()) {
            $this->beforeHttpEnd($tx);
        }

        $tx->end();

        if ($this->tracer->getConfig()->devInternal()->gcCollectCyclesAfterEveryTransaction()) {
            $this->logGcStatus();

            $numberOfCollectedCycles = gc_collect_cycles();
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Called gc_collect_cycles() because ' . OptionNames::DEV_INTERNAL
                . ' sub-option ' . DevInternalSubOptionNames::GC_COLLECT_CYCLES_AFTER_EVERY_TRANSACTION . ' is set',
                ['numberOfCollectedCycles' => $numberOfCollectedCycles]
            );

            $this->logGcStatus();
        }

        if ($this->tracer->getConfig()->devInternal()->gcMemCachesAfterEveryTransaction()) {
            $numberOfBytesFreed = gc_mem_caches();
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Called gc_mem_caches() because ' . OptionNames::DEV_INTERNAL
                . ' sub-option ' . DevInternalSubOptionNames::GC_MEM_CACHES_AFTER_EVERY_TRANSACTION . ' is set',
                ['numberOfBytesFreed' => $numberOfBytesFreed]
            );
        }
    }

    private static function isCliScript(): bool
    {
        return PHP_SAPI === 'cli';
    }

    private function discoverCliName(): string
    {
        global $argc, $argv;

        if (
            !isset($argc)
            || ($argc <= 0)
            || !isset($argv)
            || (count($argv) == 0)
            || !is_string($argv[0])
            || TextUtil::isEmptyString($argv[0])
        ) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Could not discover CLI script name - using default transaction name',
                ['DEFAULT_NAME' => self::DEFAULT_NAME]
            );
            return self::DEFAULT_NAME;
        }

        $cliScriptName = basename($argv[0]);
        if (
            ($argc < 2)
            || (count($argv) < 2)
            || ($cliScriptName !== self::LARAVEL_ARTISAN_COMMAND_SCRIPT)
        ) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Using CLI script name as transaction name',
                ['cliScriptName' => $cliScriptName, 'argc' => $argc, 'argv' => $argv]
            );
            return $cliScriptName;
        }

        $txName = $cliScriptName . ' ' . $argv[1];
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'CLI script is Laravel ' . self::LARAVEL_ARTISAN_COMMAND_SCRIPT . ' command with arguments'
            . ' - including the first argument in transaction name',
            ['txName' => $txName, 'argc' => $argc, 'argv' => $argv]
        );
        return $txName;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    private function getOptionalServerVarElement(string $key)
    {
        /** @noinspection PhpIssetCanBeReplacedWithCoalesceInspection */
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

    private function getMandatoryServerVarStringElement(string $key): ?string
    {
        $val = $this->getMandatoryServerVarElement($key);
        if ($val === null) {
            /** @noinspection PhpExpressionAlwaysNullInspection */
            return $val;
        }

        if (!is_string($val)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                '$_SERVER contains `' . $key . '\' key but the value is not a string',
                ['value type' => DbgUtil::getType($val)]
            );
            return null;
        }

        return $val;
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

        $urlGroupsMatcher = $this->tracer->getConfig()->urlGroups();
        $urlPath = $this->urlParts->path;

        $urlPathGroup = WildcardListMatcher::matchNullable($urlGroupsMatcher, $urlPath);
        if ($urlPathGroup !== null) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'For transaction name URL path is mapped to matched URL group',
                [
                    'urlPath'                                  => $urlPath,
                    'matched URL group'                        => $urlPathGroup,
                    OptionNames::URL_GROUPS . ' configuration' => $urlGroupsMatcher,
                ]
            );
        }

        if ($urlPathGroup === null) {
            $urlPathGroup = $urlPath;
        }

        $name = ($this->httpMethod === null)
            ? $urlPathGroup
            : ($this->httpMethod . ' ' . $urlPathGroup);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Successfully discovered HTTP data to derive transaction name', ['name' => $name]);

        return $name;
    }

    private function discoverStartTime(float $requestInitStartTime): float
    {
        $serverRequestTimeAsString = self::getMandatoryServerVarElement('REQUEST_TIME_FLOAT');
        if ($serverRequestTimeAsString === null) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Using requestInitStartTime for transaction start time'
                . ' because $_SERVER[\'REQUEST_TIME_FLOAT\'] is not set',
                ['requestInitStartTime' => $requestInitStartTime]
            );
            return $requestInitStartTime;
        }

        $serverRequestTimeInSeconds = floatval($serverRequestTimeAsString);
        $serverRequestTimeInMicroseconds = $serverRequestTimeInSeconds * TimeUtil::NUMBER_OF_MICROSECONDS_IN_SECOND;
        if ($requestInitStartTime < $serverRequestTimeInMicroseconds) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Using requestInitStartTime for transaction start time'
                . ' because $_SERVER[\'REQUEST_TIME_FLOAT\'] is later'
                . ' (further into the future) than requestInitStartTime',
                [
                    'requestInitStartTime'             => $requestInitStartTime,
                    '$_SERVER[\'REQUEST_TIME_FLOAT\']' => $serverRequestTimeInMicroseconds,
                    '$_SERVER[\'REQUEST_TIME_FLOAT\'] - requestInitStartTime (seconds)'
                                                       => TimeUtil::microsecondsToSeconds(
                                                           $serverRequestTimeInMicroseconds - $requestInitStartTime
                                                       ),
                ]
            );
            return $requestInitStartTime;
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Using $_SERVER[\'REQUEST_TIME_FLOAT\'] for transaction start time',
            [
                '$_SERVER[\'REQUEST_TIME_FLOAT\']' => $serverRequestTimeInMicroseconds,
                'requestInitStartTime'             => $requestInitStartTime,
                'requestInitStartTime - $_SERVER[\'REQUEST_TIME_FLOAT\'] (seconds)'
                                                   => TimeUtil::microsecondsToSeconds(
                                                       $serverRequestTimeInMicroseconds - $requestInitStartTime
                                                   ),
            ]
        );

        return $serverRequestTimeInMicroseconds;
    }

    /**
     * @return array<string, string>
     */
    private function getDistributedTracingHeaders(): array
    {
        $result = [];
        $traceParentHeaderValue = $this->getHttpHeader(HttpDistributedTracing::TRACE_PARENT_HEADER_NAME);
        if ($traceParentHeaderValue === null) {
            return [];
        }
        $result[HttpDistributedTracing::TRACE_PARENT_HEADER_NAME] = $traceParentHeaderValue;

        $traceStateHeaderValue = $this->getHttpHeader(HttpDistributedTracing::TRACE_STATE_HEADER_NAME);
        if ($traceStateHeaderValue !== null) {
            $result[HttpDistributedTracing::TRACE_STATE_HEADER_NAME] = $traceStateHeaderValue;
        }

        return $result;
    }

    private function getHttpHeader(string $headerName): ?string
    {
        $headerKey = 'HTTP_' . strtoupper($headerName);

        $traceParentHeaderValue = self::getOptionalServerVarElement($headerKey);
        if ($traceParentHeaderValue === null) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Incoming ' . $headerName . ' HTTP request header not found');
            return null;
        }

        if (!is_string($traceParentHeaderValue)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                '$_SERVER contains `' . $headerKey . '\' key but the value is not a string',
                ['value type' => DbgUtil::getType($traceParentHeaderValue)]
            );
            return null;
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Incoming ' . HttpDistributedTracing::TRACE_PARENT_HEADER_NAME . ' HTTP request header found',
            ['traceParentHeaderValue' => $traceParentHeaderValue]
        );

        return $traceParentHeaderValue;
    }

    private function discoverHttpStatusCode(): ?int
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

        return $statusCode;
    }

    private function discoverHttpResult(TransactionInterface $tx): void
    {
        $httpStatusCode = $this->discoverHttpStatusCode();
        if ($httpStatusCode === null) {
            return;
        }

        $httpStatusCode100s = intdiv($httpStatusCode, 100);
        $result = 'HTTP ' . $httpStatusCode100s . 'xx';
        $tx->setResult($result);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Discovered result for HTTP transaction',
            ['httpStatusCode' => $httpStatusCode, 'result' => $result, 'httpStatusCode100s' => $httpStatusCode100s]
        );
    }

    private function discoverHttpOutcome(TransactionInterface $tx): void
    {
        $httpStatusCode = $this->discoverHttpStatusCode();
        if ($httpStatusCode === null) {
            return;
        }

        $outcome = (500 <= $httpStatusCode && $httpStatusCode < 600)
            ? Constants::OUTCOME_FAILURE
            : Constants::OUTCOME_SUCCESS;
        $tx->setOutcome($outcome);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Discovered outcome for HTTP transaction',
            ['httpStatusCode' => $httpStatusCode, 'outcome' => $outcome]
        );
    }
}
