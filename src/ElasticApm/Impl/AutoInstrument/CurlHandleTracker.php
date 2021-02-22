<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Closure;
use Elastic\Apm\DistributedTracingData;
use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\HttpDistributedTracing;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\Assert;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\EnabledAssertProxy;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\InternalFailureException;
use Elastic\Apm\Impl\Util\UrlUtil;
use Elastic\Apm\SpanInterface;

use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_HTTPGET;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_NOBODY;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_PUT;
use const CURLOPT_URL;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class CurlHandleTracker implements LoggableInterface
{
    use InterceptedCallTrackerTrait;
    use LoggableTrait;

    private const GET_HTTP_METHOD = 'GET';
    private const HEAD_HTTP_METHOD = 'HEAD';
    private const POST_HTTP_METHOD = 'POST';
    private const PUT_HTTP_METHOD = 'PUT';

    /** @var Tracer */
    private $tracer;

    /** @var Logger */
    private $logger;

    /** @var resource */
    private $curlHandle;

    /** @var string|null */
    private $url = null;

    /** @var string|null */
    private $httpMethod = 'GET';

    /** @var mixed[] */
    private $headersSetByApp = [];

    /** @var mixed[]|null */
    private $savedHeadersBeforeInjection = null;

    /** @var SpanInterface|null */
    private $span = null;

    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;

        $this->logger = $tracer->loggerFactory()->loggerForClass(
            LogCategory::AUTO_INSTRUMENTATION,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    public function copy(): CurlHandleTracker
    {
        $copy = new CurlHandleTracker($this->tracer);

        $copy->url = $this->url;
        $copy->httpMethod = $this->httpMethod;
        $copy->headersSetByApp = $this->headersSetByApp;

        return $copy;
    }

    /**
     * @param mixed[] $interceptedCallArgs
     */
    public function curlInitPreHook(array $interceptedCallArgs): void
    {
        if (count($interceptedCallArgs) !== 0) {
            $this->setUrl($interceptedCallArgs[0]);
        }

        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Exiting');
    }

    /**
     * @param mixed $value
     */
    private function setUrl($value): void
    {
        $this->verifyValueType(is_string($value), 'string', $value);
        $this->url = $value;
    }

    /**
     * @param mixed $returnValue
     *
     * @return int|null
     */
    public function setHandle($returnValue): ?int
    {
        if (!is_resource($returnValue)) {
            return null;
        }

        $this->curlHandle = $returnValue;

        return intval($this->curlHandle);
    }

    /**
     * @param string  $functionName
     * @param mixed[] $interceptedCallArgs
     */
    public function preHook(string $functionName, array $interceptedCallArgs): void
    {
        ($assertProxy = Assert::ifEnabled())
        && $this->assertCurlHandleInArgsMatches($assertProxy, $functionName, $interceptedCallArgs);

        switch ($functionName) {
            case CurlAutoInstrumentation::CURL_SETOPT:
            case CurlAutoInstrumentation::CURL_SETOPT_ARRAY:
                // nothing to do until post-hook when we will know if the call succeeded
                return;

            case CurlAutoInstrumentation::CURL_EXEC:
                $this->curlExecPreHook();
                return;

            default:
                throw new InternalFailureException(
                    ExceptionUtil::buildMessage(
                        'Unexpected function name',
                        ['functionName' => $functionName, 'this' => $this]
                    )
                );
        }
    }

    /**
     * @param string  $functionName
     * @param int     $numberOfStackFramesToSkip
     * @param mixed[] $interceptedCallArgs
     * @param mixed   $returnValue
     */
    public function postHook(
        string $functionName,
        int $numberOfStackFramesToSkip,
        array $interceptedCallArgs,
        $returnValue
    ): void {
        ($assertProxy = Assert::ifEnabled())
        && $this->assertCurlHandleInArgsMatches($assertProxy, $functionName, $interceptedCallArgs);

        switch ($functionName) {
            case CurlAutoInstrumentation::CURL_SETOPT:
                $this->curlSetOptPostHook($interceptedCallArgs, $returnValue);
                return;

            case CurlAutoInstrumentation::CURL_SETOPT_ARRAY:
                $this->curlSetOptArrayPostHook($interceptedCallArgs, $returnValue);
                return;

            case CurlAutoInstrumentation::CURL_EXEC:
                $this->curlExecPostHook($numberOfStackFramesToSkip + 1, $returnValue);
                return;

            default:
                throw new InternalFailureException(
                    ExceptionUtil::buildMessage(
                        'Unexpected function name',
                        ['functionName' => $functionName, 'this' => $this]
                    )
                );
        }
    }

    /**
     * @param EnabledAssertProxy $assertProxy
     * @param string             $dbgFunctionName
     * @param mixed[]            $interceptedCallArgs
     *
     * @return bool
     */
    private function assertCurlHandleInArgsMatches(
        EnabledAssertProxy $assertProxy,
        string $dbgFunctionName,
        array $interceptedCallArgs
    ): bool {
        $curlHandle
            = CurlAutoInstrumentation::extractCurlHandleFromArgs($this->logger, $dbgFunctionName, $interceptedCallArgs);

        return
            $assertProxy->that(intval($curlHandle) === intval($this->curlHandle))
            && $assertProxy->withContext(
                'intval($curlHandle) === intval($this->curlHandle)',
                [
                    'intval($curlHandle)'       => intval($curlHandle),
                    'intval($this->curlHandle)' => intval($this->curlHandle),
                    'this'                      => $this,
                ]
            );
    }

    /**
     * @param bool   $isOfExpectedType
     * @param string $dbgExpectedType
     * @param mixed  $dbgValue
     *
     * @return bool
     */
    private function verifyValueType(bool $isOfExpectedType, string $dbgExpectedType, $dbgValue): bool
    {
        if (!$isOfExpectedType) {
            throw new InternalFailureException(
                ExceptionUtil::buildMessage(
                    'Unexpected value type',
                    [
                        'expectedType' => $dbgExpectedType,
                        'actualType'   => DbgUtil::getType($dbgValue),
                        'value'        => $this->logger->possiblySecuritySensitive($dbgValue),
                        'this'         => $this,
                    ]
                )
            );
        }

        return true;
    }

    /**
     * @param mixed $returnValue
     *
     * @return bool
     */
    private function isSuccess($returnValue): bool
    {
        $this->verifyValueType(is_bool($returnValue), 'bool', $returnValue);
        return $returnValue;
    }

    /**
     * @param int     $expectedMinNumberOfArgs
     * @param mixed[] $interceptedCallArgs
     *
     * @return void
     */
    private function verifyMinNumberOfArguments(int $expectedMinNumberOfArgs, array $interceptedCallArgs): void
    {
        if (count($interceptedCallArgs) < $expectedMinNumberOfArgs) {
            throw new InternalFailureException(
                ExceptionUtil::buildMessage(
                    'Number of arguments is less than expected',
                    [
                        'expectedMinNumberOfArgs' => $expectedMinNumberOfArgs,
                        'actual'                  => count($interceptedCallArgs),
                        'interceptedCallArgs'     => $this->logger->possiblySecuritySensitive($interceptedCallArgs),
                        'this'                    => $this,
                    ]
                )
            );
        }
    }

    /**
     * @param mixed[] $interceptedCallArgs
     * @param mixed   $returnValue
     */
    private function curlSetOptPostHook(array $interceptedCallArgs, $returnValue): void
    {
        if (!$this->isSuccess($returnValue)) {
            return;
        }

        $this->verifyMinNumberOfArguments(2, $interceptedCallArgs);

        $this->processSetOpt(
            $interceptedCallArgs[1],
            /**
             * @return mixed
             */
            function () use ($interceptedCallArgs) {
                $this->verifyMinNumberOfArguments(3, $interceptedCallArgs);
                return $interceptedCallArgs[2];
            }
        );
    }

    /**
     * @param mixed   $optionId
     * @param Closure $getOptionValue
     *
     * @phpstan-param Closure(): mixed $getOptionValue
     */
    private function processSetOpt($optionId, Closure $getOptionValue): void
    {
        $this->verifyValueType(is_int($optionId), 'int', $optionId);
        switch ($optionId) {
            case CURLOPT_CUSTOMREQUEST:
                $optionValue = $getOptionValue();
                $this->verifyValueType(is_string($optionValue), 'string', $optionValue);
                $this->httpMethod = $optionValue;
                break;

            case CURLOPT_HTTPHEADER:
                $optionValue = $getOptionValue();
                $this->verifyValueType(is_array($optionValue), 'array', $optionValue);
                $this->headersSetByApp = $optionValue;
                break;

            case CURLOPT_HTTPGET:
                // Based on https://github.com/curl/curl/blob/curl-7_73_0/lib/setopt.c#L841
                $this->httpMethod = self::GET_HTTP_METHOD;
                break;

            case CURLOPT_NOBODY:
                $optionValue = $getOptionValue();
                // Based on https://github.com/curl/curl/blob/curl-7_73_0/lib/setopt.c#L269
                if ($optionValue) {
                    $this->httpMethod = self::HEAD_HTTP_METHOD;
                } elseif ($this->httpMethod === self::HEAD_HTTP_METHOD) {
                    $this->httpMethod = self::GET_HTTP_METHOD;
                }
                break;

            case CURLOPT_POST:
                // Based on https://github.com/curl/curl/blob/curl-7_73_0/lib/setopt.c#L616
                $optionValue = $getOptionValue();
                $this->httpMethod = $optionValue ? self::POST_HTTP_METHOD : self::GET_HTTP_METHOD;
                break;

            case CURLOPT_POSTFIELDS:
                // Based on https://github.com/curl/curl/blob/curl-7_73_0/lib/setopt.c#L486
                $this->httpMethod = self::POST_HTTP_METHOD;
                break;

            case CURLOPT_PUT:
                // Based on https://github.com/curl/curl/blob/curl-7_73_0/lib/setopt.c#L292
                $optionValue = $getOptionValue();
                $this->httpMethod = $optionValue ? self::PUT_HTTP_METHOD : self::GET_HTTP_METHOD;
                break;

            case CURLOPT_URL:
                $this->setUrl($getOptionValue());
                break;
        }
    }

    /**
     * @param mixed[] $interceptedCallArgs
     * @param mixed   $returnValue
     */
    private function curlSetOptArrayPostHook(array $interceptedCallArgs, $returnValue): void
    {
        if (!$this->isSuccess($returnValue)) {
            return;
        }

        $this->verifyMinNumberOfArguments(2, $interceptedCallArgs);
        $optionsIdToValue = $interceptedCallArgs[1];
        $this->verifyValueType(is_array($optionsIdToValue), 'array', $optionsIdToValue);

        foreach ($optionsIdToValue as $optionId => $optionValue) {
            $this->processSetOpt(
                $optionId,
                /**
                 * @return mixed
                 */
                function () use ($optionValue) {
                    return $optionValue;
                }
            );
        }
    }

    private function curlExecPreHook(): void
    {
        $spanName
            = (is_null($this->httpMethod) ? '<' . 'HTTP METHOD UNKNOWN' . '>' : $this->httpMethod)
              . ' '
              . (is_null($this->url) ? ('<' . 'UNKNOWN HOST' . '>') : UrlUtil::extractHostPart($this->url));

        $isHttp = is_null($this->url) ? false : UrlUtil::isHttp($this->url);
        $this->span = ElasticApm::getCurrentTransaction()->beginCurrentSpan(
            $spanName,
            Constants::SPAN_TYPE_EXTERNAL,
            /* subtype: */
            $isHttp ? Constants::SPAN_TYPE_EXTERNAL_SUBTYPE_HTTP : null
        );

        if (!is_null($this->httpMethod)) {
            $this->span->context()->http()->setMethod($this->httpMethod);
        }
        if (!is_null($this->url)) {
            $this->span->context()->http()->setUrl($this->url);
        }

        if ($isHttp) {
            $distributedTracingData = $this->span->getDistributedTracingData();
            if (!is_null($distributedTracingData)) {
                $this->injectDistributedTracingHeader($distributedTracingData);
            }
        }
    }

    /**
     * @param int   $numberOfStackFramesToSkip
     * @param mixed $returnValue
     */
    private function curlExecPostHook(int $numberOfStackFramesToSkip, $returnValue): void
    {
        if (!is_null($this->savedHeadersBeforeInjection)) {
            $this->headersSetByApp = $this->savedHeadersBeforeInjection;
            $this->savedHeadersBeforeInjection = null;

            $setOptRetVal = curl_setopt($this->curlHandle, CURLOPT_HTTPHEADER, $this->headersSetByApp);
            if ($setOptRetVal) {
                ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Successfully restored headers as they were before injection');
            } else {
                $this->savedHeadersBeforeInjection = null;
                ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Failed to restore headers as they were before injection');
            }
        }

        assert(!is_null($this->span));
        $statusCode = curl_getinfo($this->curlHandle, CURLINFO_RESPONSE_CODE);
        if (is_int($statusCode)) {
            $this->span->context()->http()->setStatusCode($statusCode);
        } else {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Failed to get response status code');
        }

        self::endSpan(
            $numberOfStackFramesToSkip + 1,
            $this->span,
            false /* <- hasExitedByException */,
            $returnValue
        );
    }

    /**
     * @param DistributedTracingData $data
     */
    private function injectDistributedTracingHeader(DistributedTracingData $data): void
    {
        $traceParentHeaderValue = HttpDistributedTracing::buildTraceParentHeader($data);
        $headers = array_merge(
            $this->headersSetByApp,
            [HttpDistributedTracing::TRACE_PARENT_HEADER_NAME . ': ' . $traceParentHeaderValue]
        );

        $logger = $this->logger->inherit()->addAllContext(
            [
                'traceParentHeaderValue' => $traceParentHeaderValue,
                'headers'                => $this->logger->possiblySecuritySensitive($headers),
            ]
        );

        ($loggerProxy = $logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Injecting outgoing ' . HttpDistributedTracing::TRACE_PARENT_HEADER_NAME . ' HTTP request header...'
        );

        $this->savedHeadersBeforeInjection = $this->headersSetByApp;
        $setOptRetVal = curl_setopt($this->curlHandle, CURLOPT_HTTPHEADER, $headers);
        if ($setOptRetVal) {
            ($loggerProxy = $logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Successfully injected outgoing '
                . HttpDistributedTracing::TRACE_PARENT_HEADER_NAME . ' HTTP request header'
            );
        } else {
            $this->savedHeadersBeforeInjection = null;
            ($loggerProxy = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Failed to inject outgoing '
                . HttpDistributedTracing::TRACE_PARENT_HEADER_NAME . ' HTTP request header'
            );
        }
    }

    /**
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return ['logger', 'tracer'];
    }
}
