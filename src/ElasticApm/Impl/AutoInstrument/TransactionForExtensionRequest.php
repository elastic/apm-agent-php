<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\DistributedTracingData;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\HttpDistributedTracing;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\ArrayUtil;
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

    private function discoverHttpName(): string
    {
        if (!is_null($requestUri = ArrayUtil::getValueIfKeyExistsElse('REQUEST_URI', $_SERVER, null))) {
            $name = '';
            if (!is_null($requestMethod = ArrayUtil::getValueIfKeyExistsElse('REQUEST_METHOD', $_SERVER, null))) {
                $name = $requestMethod . ' ';
            }
            $name .= $requestUri;

            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Successfully discovered HTTP data to derive transaction name', ['name' => $name]);

            return $name;
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Could not discover HTTP data to derive transaction name - using default transaction name',
            ['DEFAULT_NAME' => self::DEFAULT_NAME]
        );
        return self::DEFAULT_NAME;
    }

    private function discoverTimestamp(float $requestInitStartTime): float
    {
        $serverRequestTimeAsString = ArrayUtil::getValueIfKeyExistsElse('REQUEST_TIME_FLOAT', $_SERVER, null);
        if (!is_null($serverRequestTimeAsString)) {
            $serverRequestTimeInSeconds = floatval($serverRequestTimeAsString);
            $serverRequestTimeInMicroseconds = $serverRequestTimeInSeconds * 1000000;

            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Using $_SERVER[\'REQUEST_TIME_FLOAT\'] for transaction serverRequestTimeInMicroseconds',
                ['serverRequestTimeInMicroseconds' => $serverRequestTimeInMicroseconds]
            );

            return $serverRequestTimeInMicroseconds;
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Using requestInitStartTime for transaction serverRequestTimeInMicroseconds',
            ['requestInitStartTime' => $requestInitStartTime]
        );
        return $requestInitStartTime;
    }

    private function discoverIncomingDistributedTracingData(): ?DistributedTracingData
    {
        $headerName = HttpDistributedTracing::TRACE_PARENT_HEADER_NAME;
        $traceParentHeaderKey = 'HTTP_' . strtoupper($headerName);
        if (!array_key_exists($traceParentHeaderKey, $_SERVER)) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Incoming ' . $headerName . ' HTTP request header not found');
            return null;
        }

        $traceParentHeaderValue = $_SERVER[$traceParentHeaderKey];
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Incoming ' . HttpDistributedTracing::TRACE_PARENT_HEADER_NAME . ' HTTP request header found',
            ['traceParentHeaderValue' => $traceParentHeaderValue]
        );

        return $this->tracer->httpDistributedTracing()->parseTraceParentHeader($traceParentHeaderValue);
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
