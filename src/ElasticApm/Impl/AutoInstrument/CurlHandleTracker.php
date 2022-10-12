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

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Closure;
use Elastic\Apm\CustomErrorData;
use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\AutoInstrument\Util\AutoInstrumentationUtil;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\Assert;
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
    use LoggableTrait;

    private const GET_HTTP_METHOD = 'GET';
    private const HEAD_HTTP_METHOD = 'HEAD';
    private const POST_HTTP_METHOD = 'POST';
    private const PUT_HTTP_METHOD = 'PUT';

    /** @var Tracer */
    private $tracer;

    /** @var Logger */
    private $logger;

    /** @var AutoInstrumentationUtil */
    private $util;

    /** @var CurlHandleWrapped */
    private $curlHandle;

    /** @var ?string */
    private $url = null;

    /** @var ?string */
    private $httpMethod = 'GET';

    /** @var mixed[] */
    private $headersSetByApp = [];

    /** @var SpanInterface */
    private $span;

    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;

        $this->logger = $tracer->loggerFactory()->loggerForClass(
            LogCategory::AUTO_INSTRUMENTATION,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        $this->util = new AutoInstrumentationUtil($tracer->loggerFactory());
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
        if (!$this->util->verifyIsString($value)) {
            return;
        }
        /** @var string $value */
        $this->url = $value;
    }

    /**
     * @param mixed $curlHandle
     *
     * @return int|null
     */
    public function setHandle($curlHandle): ?int
    {
        if ($curlHandle === false) {
            return null;
        }
        // Prior to PHP 8 $curlHandle is a resource
        // For PHP 8+ $curlHandle is an instance of CurlHandle class
        /** @var resource|object $curlHandle */

        $this->curlHandle = new CurlHandleWrapped($curlHandle);

        return $this->curlHandle->asInt();
    }

    /**
     * @param string  $dbgFuncName
     * @param int     $funcId
     * @param mixed[] $interceptedCallArgs
     */
    public function preHook(string $dbgFuncName, int $funcId, array $interceptedCallArgs): void
    {
        ($assertProxy = Assert::ifEnabled())
        && $this->assertCurlHandleInArgsMatches($assertProxy, $dbgFuncName, $interceptedCallArgs);

        switch ($funcId) {
            case CurlAutoInstrumentation::CURL_SETOPT_ID:
            case CurlAutoInstrumentation::CURL_SETOPT_ARRAY_ID:
                // nothing to do until post-hook when we will know if the call succeeded
                return;

            case CurlAutoInstrumentation::CURL_EXEC_ID:
                $this->curlExecPreHook();
                return;

            default:
                throw new InternalFailureException(
                    ExceptionUtil::buildMessage(
                        'Unexpected function name',
                        ['functionName' => $dbgFuncName, 'this' => $this]
                    )
                );
        }
    }

    /**
     * @param string  $dbgFuncName
     * @param int     $funcId
     * @param int     $numberOfStackFramesToSkip
     * @param mixed[] $interceptedCallArgs
     * @param mixed   $returnValue
     */
    public function postHook(
        string $dbgFuncName,
        int $funcId,
        int $numberOfStackFramesToSkip,
        array $interceptedCallArgs,
        $returnValue
    ): void {
        ($assertProxy = Assert::ifEnabled())
        && $this->assertCurlHandleInArgsMatches($assertProxy, $dbgFuncName, $interceptedCallArgs);

        switch ($funcId) {
            case CurlAutoInstrumentation::CURL_SETOPT_ID:
                $this->curlSetOptPostHook($interceptedCallArgs, $returnValue);
                return;

            case CurlAutoInstrumentation::CURL_SETOPT_ARRAY_ID:
                $this->curlSetOptArrayPostHook($interceptedCallArgs, $returnValue);
                return;

            case CurlAutoInstrumentation::CURL_EXEC_ID:
                $this->curlExecPostHook($numberOfStackFramesToSkip + 1, $returnValue);
                return;

            default:
                throw new InternalFailureException(
                    ExceptionUtil::buildMessage(
                        'Unexpected function name',
                        ['funcId' => $funcId, 'this' => $this]
                    )
                );
        }
    }

    /**
     * @param EnabledAssertProxy $assertProxy
     * @param string             $dbgFuncName
     * @param mixed[]            $interceptedCallArgs
     *
     * @return bool
     */
    private function assertCurlHandleInArgsMatches(
        EnabledAssertProxy $assertProxy,
        string $dbgFuncName,
        array $interceptedCallArgs
    ): bool {
        $curlHandle
            = CurlAutoInstrumentation::extractCurlHandleFromArgs($this->logger, $dbgFuncName, $interceptedCallArgs);

        $thisCurlHandleAsInt = $this->curlHandle->asInt();
        $argsCurlHandleAsInt = CurlHandleWrapped::nullableAsInt($curlHandle);
        return
            $assertProxy->that($thisCurlHandleAsInt === $argsCurlHandleAsInt)
            && $assertProxy->withContext(
                '$thisCurlHandleAsInt === $argsCurlHandleAsInt',
                [
                    '$thisCurlHandleAsInt' => $thisCurlHandleAsInt,
                    '$argsCurlHandleAsInt' => $argsCurlHandleAsInt,
                    'this'                 => $this,
                ]
            );
    }

    /**
     * @param mixed $returnValue
     *
     * @return bool
     */
    private function isSuccess($returnValue): bool
    {
        if (!$this->util->verifyIsBool($returnValue)) {
            return false;
        }
        /** @var bool $returnValue */
        return $returnValue;
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

        if (!$this->util->verifyMinArgsCount(2, $interceptedCallArgs)) {
            return;
        }

        $this->processSetOpt(
            $interceptedCallArgs[1],
            /**
             * @param mixed &$optVal
             *
             * @return bool
             */
            function (&$optVal) use ($interceptedCallArgs) {
                if (!$this->util->verifyMinArgsCount(3, $interceptedCallArgs)) {
                    return false;
                }
                $optVal = $interceptedCallArgs[2];
                return true;
            }
        );
    }

    /**
     * @param mixed                         $optionId
     * @param Closure(mixed &$optVal): bool $getOptionValue
     */
    private function processSetOpt($optionId, Closure $getOptionValue): void
    {
        if (!$this->util->verifyIsInt($optionId)) {
            return;
        }

        switch ($optionId) {
            case CURLOPT_CUSTOMREQUEST:
                $optionValue = null;
                if (!$getOptionValue(/* ref */ $optionValue)) {
                    return;
                }
                if (!$this->util->verifyIsString($optionValue)) {
                    return;
                }
                /** @var string $optionValue */
                $this->httpMethod = $optionValue;
                break;

            case CURLOPT_HTTPHEADER:
                $optionValue = null;
                if (!$getOptionValue(/* ref */ $optionValue)) {
                    return;
                }
                if (!$this->util->verifyIsArray($optionValue)) {
                    return;
                }
                /** @var mixed[] $optionValue */
                $this->headersSetByApp = $optionValue;
                break;

            case CURLOPT_HTTPGET:
                // Based on https://github.com/curl/curl/blob/curl-7_73_0/lib/setopt.c#L841
                $this->httpMethod = self::GET_HTTP_METHOD;
                break;

            case CURLOPT_NOBODY:
                $optionValue = null;
                if (!$getOptionValue(/* ref */ $optionValue)) {
                    return;
                }
                // Based on https://github.com/curl/curl/blob/curl-7_73_0/lib/setopt.c#L269
                if ($optionValue) {
                    $this->httpMethod = self::HEAD_HTTP_METHOD;
                } elseif ($this->httpMethod === self::HEAD_HTTP_METHOD) {
                    $this->httpMethod = self::GET_HTTP_METHOD;
                }
                break;

            case CURLOPT_POST:
                // Based on https://github.com/curl/curl/blob/curl-7_73_0/lib/setopt.c#L616
                $optionValue = null;
                if (!$getOptionValue(/* ref */ $optionValue)) {
                    return;
                }
                $this->httpMethod = $optionValue ? self::POST_HTTP_METHOD : self::GET_HTTP_METHOD;
                break;

            case CURLOPT_POSTFIELDS:
                // Based on https://github.com/curl/curl/blob/curl-7_73_0/lib/setopt.c#L486
                $this->httpMethod = self::POST_HTTP_METHOD;
                break;

            case CURLOPT_PUT:
                // Based on https://github.com/curl/curl/blob/curl-7_73_0/lib/setopt.c#L292
                $optionValue = null;
                if (!$getOptionValue(/* ref */ $optionValue)) {
                    return;
                }
                $this->httpMethod = $optionValue ? self::PUT_HTTP_METHOD : self::GET_HTTP_METHOD;
                break;

            case CURLOPT_URL:
                $optionValue = null;
                if (!$getOptionValue(/* ref */ $optionValue)) {
                    return;
                }
                $this->setUrl($optionValue);
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

        if (!$this->util->verifyMinArgsCount(2, $interceptedCallArgs)) {
            return;
        }
        $optionsIdToValue = $interceptedCallArgs[1];
        if (!$this->util->verifyIsArray($optionsIdToValue)) {
            return;
        }
        /** @var array<mixed, mixed> $optionsIdToValue */

        foreach ($optionsIdToValue as $optionId => $optionValue) {
            $this->processSetOpt(
                $optionId,
                /**
                 * @param mixed &$optVal
                 *
                 * @return bool
                 */
                function (&$optVal) use ($optionValue): bool {
                    $optVal = $optionValue;
                    return true;
                }
            );
        }
    }

    private function curlExecPreHook(): void
    {
        $httpMethod = $this->httpMethod ?? ('<' . 'UNKNOWN HTTP METHOD' . '>');
        $host = null;
        if ($this->url !== null) {
            $host = UrlUtil::extractHostPart($this->url);
        }
        $host = $host ?? ('<' . 'UNKNOWN HOST' . '>');

        $spanName = $httpMethod . ' ' . $host;

        $isHttp = ($this->url !== null) && UrlUtil::isHttp($this->url);
        $this->span = ElasticApm::getCurrentTransaction()->beginCurrentSpan(
            $spanName,
            Constants::SPAN_TYPE_EXTERNAL,
            /* subtype: */
            $isHttp ? Constants::SPAN_SUBTYPE_HTTP : null
        );

        $this->setContextPreHook();

        if ($isHttp) {
            $headersToInjectFormattedLines = [];
            $this->span->injectDistributedTracingHeaders(
                function (string $headerName, string $headerValue) use (&$headersToInjectFormattedLines): void {
                    $headersToInjectFormattedLines[] = $headerName . ': ' . $headerValue;
                }
            );
            if (!ArrayUtil::isEmpty($headersToInjectFormattedLines)) {
                $this->injectDistributedTracingHeaders($headersToInjectFormattedLines);
            }
        }
    }

    private static function appendOrSetString(?string &$accumStr, ?string $substrToAppend): void
    {
        if ($substrToAppend === null) {
            return;
        }

        if ($accumStr === null) {
            $accumStr = $substrToAppend;
        } else {
            $accumStr .= $substrToAppend;
        }
    }

    private function buildContextDestinationServiceName(
        ?string $scheme,
        ?string $host,
        ?int $port,
        ?int $defaultPortForScheme
    ): ?string {
        /** @var ?string $result */
        $result = null;

        if ($scheme !== null) {
            $result = $scheme . '//';
        }

        self::appendOrSetString(/* ref */ $result, $host);

        if ($port !== null && $port !== $defaultPortForScheme) {
            self::appendOrSetString(/* ref */ $result, ':' . $port);
        }

        return $result;
    }

    private function buildContextDestinationServiceResource(
        ?string $host,
        ?int $port,
        ?int $defaultPortForScheme
    ): ?string {
        /** @var ?string $result */
        $result = null;

        self::appendOrSetString(/* ref */ $result, $host);

        /** @var ?string $portSuffix */
        $portSuffix = null;
        if ($port === null) {
            if ($defaultPortForScheme !== null) {
                $portSuffix = ':' . $defaultPortForScheme;
            }
        } else {
            $portSuffix = ':' . $port;
        }
        self::appendOrSetString(/* ref */ $result, $portSuffix);

        return $result;
    }

    private function setContextDestinationService(): void
    {
        if ($this->url === null) {
            return;
        }

        $parsedUrl = parse_url($this->url);
        if (!is_array($parsedUrl)) {
            return;
        }

        $scheme = ArrayUtil::getValueIfKeyExistsElse('scheme', $parsedUrl, null);
        if ($scheme !== null && !is_string($scheme)) {
            $scheme = null;
        }
        $host = ArrayUtil::getValueIfKeyExistsElse('host', $parsedUrl, null);
        if ($host !== null && !is_string($host)) {
            $host = null;
        }
        $port = ArrayUtil::getValueIfKeyExistsElse('port', $parsedUrl, null);
        if ($port !== null && !is_int($port)) {
            $port = null;
        }
        $defaultPortForScheme = ($scheme === null) ? null : UrlUtil::defaultPortForScheme($scheme);

        $name = $this->buildContextDestinationServiceName($scheme, $host, $port, $defaultPortForScheme);
        $resource = $this->buildContextDestinationServiceResource($host, $port, $defaultPortForScheme);

        if ($name !== null && $resource !== null) {
            $this->span->context()->destination()->setService($name, $resource, Constants::SPAN_TYPE_EXTERNAL);
        }
    }

    private function setContextDestination(): void
    {
        $this->setContextDestinationService();
    }

    private function setContextPreHook(): void
    {
        if ($this->httpMethod !== null) {
            $this->span->context()->http()->setMethod($this->httpMethod);
        }

        if ($this->url !== null) {
            $this->span->context()->http()->setUrl($this->url);
        }

        $this->setContextDestination();
    }

    private function setContextPostHook(): void
    {
        $errorCode = $this->curlHandle->errno();
        if ($errorCode !== 0) {
            $this->span->setOutcome(Constants::OUTCOME_FAILURE);
            $customErrorData = new CustomErrorData();
            $customErrorData->code = $errorCode;
            $customErrorData->type = curl_strerror($errorCode);
            $customErrorData->message = $this->curlHandle->error();
            $this->span->createCustomError($customErrorData);
            return;
        }

        $responseStatusCode = $this->curlHandle->getResponseStatusCode();
        if (is_int($responseStatusCode)) {
            $this->span->context()->http()->setStatusCode($responseStatusCode);
            $outcome = (400 <= $responseStatusCode && $responseStatusCode < 600)
                ? Constants::OUTCOME_FAILURE
                : Constants::OUTCOME_SUCCESS;
            $this->span->setOutcome($outcome);
        } else {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Failed to get response status code', ['responseStatusCode' => $responseStatusCode]);
        }
    }

    /**
     * @param int   $numberOfStackFramesToSkip
     * @param mixed $returnValue
     */
    private function curlExecPostHook(int $numberOfStackFramesToSkip, $returnValue): void
    {
        $this->setContextPostHook();

        AutoInstrumentationUtil::endSpan(
            $numberOfStackFramesToSkip + 1,
            $this->span,
            false /* <- hasExitedByException */,
            $returnValue
        );
    }

    /**
     * @param string[] $headersToInjectFormattedLines
     */
    private function injectDistributedTracingHeaders(array $headersToInjectFormattedLines): void
    {
        $headers = array_merge($this->headersSetByApp, $headersToInjectFormattedLines);

        $logger = $this->logger->inherit()->addAllContext(
            [
                'headersToInjectFormattedLines' => $headersToInjectFormattedLines,
                'headers'                       => $this->logger->possiblySecuritySensitive($headers),
            ]
        );

        ($loggerProxy = $logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Injecting outgoing HTTP request headers for distributed tracing...');

        $setOptRetVal = $this->curlHandle->setOpt(CURLOPT_HTTPHEADER, $headers);
        if ($setOptRetVal) {
            ($loggerProxy = $logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Successfully injected outgoing HTTP request headers for distributed tracing');
        } else {
            ($loggerProxy = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Failed to inject outgoing HTTP request headers for distributed tracing');
        }
    }

    /**
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return ['tracer'];
    }
}
