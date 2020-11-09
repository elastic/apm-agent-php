<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\InterceptedCallTrackerInterface;
use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\DistributedTracingData;
use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\HttpDistributedTracing;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\SpanInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class CurlAutoInstrumentation
{
    /** @var Tracer */
    private $tracer;

    /** @var Logger */
    private $logger;

    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;

        $this->logger = $tracer->loggerFactory()->loggerForClass(
            LogCategory::AUTO_INSTRUMENTATION,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );
    }

    public function register(RegistrationContextInterface $ctx): void
    {
        if (!extension_loaded('curl')) {
            return;
        }

        self::curlExec($ctx);
    }

    public function curlExec(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToFunction(
            'curl_exec',
            function (): InterceptedCallTrackerInterface {
                return new class ($this) implements InterceptedCallTrackerInterface {
                    use InterceptedCallTrackerTrait;

                    /** @var resource|null */
                    private $curlHandle;

                    /** @var SpanInterface */
                    private $span;

                    /** @var CurlAutoInstrumentation */
                    private $owner;

                    public function __construct(CurlAutoInstrumentation $owner)
                    {
                        $this->owner = $owner;
                    }

                    public function preHook(?object $interceptedCallThis, ...$interceptedCallArgs): void
                    {
                        self::assertInterceptedCallThisIsNull($interceptedCallThis, ...$interceptedCallArgs);

                        $this->curlHandle = count($interceptedCallArgs) != 0 && is_resource($interceptedCallArgs[0])
                            ? $interceptedCallArgs[0]
                            : null;

                        $this->span = ElasticApm::getCurrentTransaction()->beginCurrentSpan(
                            'curl_exec',
                            Constants::SPAN_TYPE_EXTERNAL,
                            Constants::SPAN_TYPE_EXTERNAL_SUBTYPE_HTTP
                        );

                        $distributedTracingData = $this->span->getDistributedTracingData();
                        if (!is_null($this->curlHandle) && !is_null($distributedTracingData)) {
                            $this->owner->injectDistributedTracingDataHeader(
                                $this->curlHandle,
                                $distributedTracingData
                            );
                        }
                    }

                    public function postHook(
                        int $numberOfStackFramesToSkip,
                        bool $hasExitedByException,
                        $returnValueOrThrown
                    ): void {
                        if (!$hasExitedByException) {
                            if (!is_null($this->curlHandle)) {
                                $info = curl_getinfo($this->curlHandle);
                                $httpCode = ArrayUtil::getValueIfKeyExistsElse('http_code', $info, null);
                                if (!is_null($httpCode)) {
                                    $this->span->context()->setLabel('HTTP status', $httpCode);
                                }
                            }
                        }

                        self::endSpan(
                            $numberOfStackFramesToSkip + 1,
                            $this->span,
                            $hasExitedByException,
                            $returnValueOrThrown
                        );
                    }
                };
            }
        );
    }

    /**
     * @param resource               $curlHandle
     * @param DistributedTracingData $data
     */
    public function injectDistributedTracingDataHeader($curlHandle, DistributedTracingData $data): void
    {
        $traceParentHeaderValue = $this->tracer->httpDistributedTracing()->buildTraceParentHeader($data);
        $headers = [HttpDistributedTracing::TRACE_PARENT_HEADER_NAME . ': ' . $traceParentHeaderValue];

        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Injecting outgoing ' . HttpDistributedTracing::TRACE_PARENT_HEADER_NAME . ' HTTP request header...',
            ['traceParentHeaderValue' => $traceParentHeaderValue]
        );

        $setOptRetVal = curl_setopt($curlHandle, CURLOPT_HTTPHEADER, $headers);

        if ($setOptRetVal) {
            ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Successfully injected outgoing '
                . HttpDistributedTracing::TRACE_PARENT_HEADER_NAME . ' HTTP request header',
                ['traceParentHeaderValue' => $traceParentHeaderValue]
            );
        } else {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Failed to inject outgoing '
                . HttpDistributedTracing::TRACE_PARENT_HEADER_NAME . ' HTTP request header',
                ['traceParentHeaderValue' => $traceParentHeaderValue]
            );
        }
    }
}
