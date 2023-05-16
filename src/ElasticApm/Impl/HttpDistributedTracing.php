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

namespace Elastic\Apm\Impl;

use Elastic\Apm\DistributedTracingData;
use Elastic\Apm\Impl\Config\FloatOptionParser;
use Elastic\Apm\Impl\Config\ParseException;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\IdValidationUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Impl\Util\TextUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class HttpDistributedTracing
{
    use StaticClassTrait;

    public const TRACE_PARENT_HEADER_NAME = 'traceparent';
    private const TRACE_PARENT_SUPPORTED_FORMAT_VERSION = '00';
    public const TRACE_PARENT_INVALID_TRACE_ID = '00000000000000000000000000000000';
    public const TRACE_PARENT_INVALID_PARENT_ID = '0000000000000000';
    private const TRACE_PARENT_SAMPLED_FLAG = 0b00000001;

    public const TRACE_STATE_HEADER_NAME = 'tracestate';
    public const TRACE_STATE_KEY_VALUE_PAIRS_SEPARATOR = ',';
    public const TRACE_STATE_MAX_PAIRS_COUNT = 32;
    public const TRACE_STATE_KEY_VALUE_SEPARATOR = '=';

    /*
     * simple-key = lcalpha 0*255( lcalpha / DIGIT / "_" / "-"/ "*" / "/" )
     * tenant-id = ( lcalpha / DIGIT ) 0*240( lcalpha / DIGIT / "_" / "-"/ "*" / "/" )
     * system-id = lcalpha 0*13( lcalpha / DIGIT / "_" / "-"/ "*" / "/" )
     *
     * @link https://www.w3.org/TR/trace-context/#key
     */
    // public const TRACE_STATE_MAX_ID_REGEX = '/[a-z\d][a-z\d_\-*/]*/';
    public const TRACE_STATE_MAX_ID_REGEX = '/^[a-z\d][a-z\d_\-*\/]*$/';
    public const TRACE_STATE_MAX_VENDOR_KEY_LENGTH = 256;
    public const TRACE_STATE_MAX_TENANT_ID_LENGTH = 241;
    public const TRACE_STATE_MAX_SYSTEM_ID_LENGTH = 14;

    public const TRACE_STATE_ELASTIC_VENDOR_KEY = 'es';
    public const TRACE_STATE_ELASTIC_VENDOR_SAMPLE_RATE_SUBKEY = 's';
    public const TRACE_STATE_ELASTIC_VENDOR_SUBKEY_VALUE_SEPARATOR = ':';
    /*
     * transaction_sample_rate configuration option has a maximum precision of 4 decimal places.
     * So sample rate's max length is 6 (for example 0.1234)
     *
     * @link https://github.com/elastic/apm/blob/main/specs/agents/tracing-sampling.md#propagation
     */
    public const TRACE_STATE_ELASTIC_VENDOR_SAMPLE_RATE_VALUE_MAX_LEN = 6;

    /** @var Logger */
    private $logger;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->loggerForClass(
            LogCategory::DISTRIBUTED_TRACING,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );
    }

    /**
     * @param string[] $traceParentHeaders
     * @param string[] $traceStateHeaders
     *
     * @return ?DistributedTracingDataInternal
     */
    public function parseHeaders(array $traceParentHeaders, array $traceStateHeaders): ?DistributedTracingDataInternal
    {
        /** @var bool */
        $isTraceParentValid = false;
        /** @var ?bool */
        $isTraceStateValid = null;
        return $this->parseHeadersImpl(
            $traceParentHeaders,
            $traceStateHeaders,
            $isTraceParentValid /* <- ref */,
            $isTraceStateValid /* <- ref */
        );
    }

    /**
     * @param string[] $traceParentHeaderValues
     * @param string[] $traceStateHeaderValues
     * @param bool     $isTraceParentValid
     * @param ?bool    $isTraceStateValid
     *
     * @return ?DistributedTracingDataInternal
     */
    public function parseHeadersImpl(
        array $traceParentHeaderValues,
        array $traceStateHeaderValues,
        bool &$isTraceParentValid,
        ?bool &$isTraceStateValid
    ): ?DistributedTracingDataInternal {
        $localLogger = $this->logger->inherit()->addAllContext(
            [
                'traceParentHeaderValues' => $traceParentHeaderValues,
                'traceStateHeaderValues' => $traceStateHeaderValues
            ]
        );
        if (count($traceParentHeaderValues) === 0) {
            ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Found no ' . self::TRACE_PARENT_HEADER_NAME . ' HTTP header so there is nothing to parse'
            );
            $isTraceParentValid = false;
            $isTraceStateValid = null;
            return null;
        }

        if (count($traceParentHeaderValues) > 1) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Found more than one ' . self::TRACE_PARENT_HEADER_NAME . ' HTTP header which is invalid'
            );
            $isTraceParentValid = false;
            $isTraceStateValid = null;
            return null;
        }

        /**
         * @link https://www.w3.org/TR/trace-context/#tracestate-header
         *
         * If the vendor failed to parse traceparent, it MUST NOT attempt to parse tracestate.
         * Note that the opposite is not true: failure to parse tracestate MUST NOT affect the parsing of traceparent.
         */

        $isTraceParentValid = ($result = $this->parseTraceParentHeader($traceParentHeaderValues[0])) !== null;
        if (!$isTraceParentValid) {
            $isTraceStateValid = null;
            return null;
        }
        /** @var DistributedTracingDataInternal $result */

        if (ArrayUtil::isEmpty($traceStateHeaderValues)) {
            $isTraceStateValid = null;
        } else {
            $isTraceStateValid = $this->parseTraceStateHeaders($traceStateHeaderValues, $result);
        }
        return $result;
    }

    private function parseTraceParentHeader(string $headerRawValue): ?DistributedTracingDataInternal
    {
        // 00-0af7651916cd43dd8448eb211c80319c-b9c7c989f97918e1-01
        // ^^ ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^ ^^^^^^^^^^^^^^^^ ^^
        // || |||||||||||||||||||||||||||||||| |||||||||||||||| -- - flagsAsString
        // || |||||||||||||||||||||||||||||||| ---------------- - parentId
        // || -------------------------------- - trace-id
        // -- - version

        $headerValue = trim($headerRawValue);

        $parentFunc = __FUNCTION__;
        $implicitContextToLog = ['headerValue' => $headerValue];
        $logParsingFailedMessage = function (
            string $reason,
            int $srcCodeLineNumber,
            array $context = []
        ) use (
            $parentFunc,
            &$implicitContextToLog
        ): void {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled($srcCodeLineNumber, $parentFunc))
            && $loggerProxy->log(
                'Failed to parse ' . self::TRACE_PARENT_HEADER_NAME . ' HTTP header: '
                . $reason,
                array_merge($context, $implicitContextToLog)
            );
        };

        $result = new DistributedTracingDataInternal();

        $expectedNumberOfParts = 4;
        $implicitContextToLog['expectedNumberOfParts'] = $expectedNumberOfParts;
        /**
         * limit is $expectedNumberOfParts + 1 because according to W3C spec requires parser to allow
         * more than $expectedNumberOfParts in the future versions
         * as long as the new parts are appended at the end after a dash
         *
         * @link https://www.w3.org/TR/trace-context/#versioning-of-traceparent
         */
        $parts = explode(/* separator: */ '-', $headerValue, /* limit: */ $expectedNumberOfParts + 1);
        $implicitContextToLog['parts'] = $parts;
        if (count($parts) < $expectedNumberOfParts) {
            $logParsingFailedMessage("the number of separated parts is less than expected", __LINE__);
            return null;
        }

        $version = $parts[0];
        $implicitContextToLog['version'] = $version;
        /**
         * @link https://www.w3.org/TR/trace-context/#version
         *
         * Version ff is forbidden
         */
        /** @noinspection PhpStrictComparisonWithOperandsOfDifferentTypesInspection */
        if (strcasecmp($version, 'ff') === 0) {
            $logParsingFailedMessage('version ff is forbidden', __LINE__);
            return null;
        }
        if (!IdValidationUtil::isValidHexNumberString($version, /* expectedSizeInBytes - 1 byte = 2 hex chars */ 1)) {
            $logParsingFailedMessage('version is not a valid 2 hex characters string', __LINE__);
            return null;
        }
        if ($version === self::TRACE_PARENT_SUPPORTED_FORMAT_VERSION && count($parts) !== $expectedNumberOfParts) {
            $logParsingFailedMessage(
                'there are more than expected number of separated parts for the current format version',
                __LINE__
            );
            return null;
        }

        $traceId = $parts[1];
        $implicitContextToLog['traceId'] = $traceId;
        if (!IdValidationUtil::isValidHexNumberString($traceId, Constants::TRACE_ID_SIZE_IN_BYTES)) {
            $logParsingFailedMessage(
                'traceId is not a valid ' . Constants::TRACE_ID_SIZE_IN_BYTES . ' bytes hex ID',
                __LINE__
            );
            return null;
        }
        if ($traceId === self::TRACE_PARENT_INVALID_TRACE_ID) {
            $logParsingFailedMessage(
                'traceId that is all bytes as zero (' . self::TRACE_PARENT_INVALID_TRACE_ID . ') is an invalid value',
                __LINE__
            );
            return null;
        }
        $result->traceId = strtolower($traceId);

        $parentId = $parts[2];
        $implicitContextToLog['parentId'] = $parentId;
        if (!IdValidationUtil::isValidHexNumberString($parentId, Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES)) {
            $logParsingFailedMessage(
                'parentId is not a valid ' . Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES . ' bytes hex ID',
                __LINE__
            );
            return null;
        }
        if ($parentId === self::TRACE_PARENT_INVALID_PARENT_ID) {
            $logParsingFailedMessage(
                'parentId that is all bytes as zero ('
                . self::TRACE_PARENT_INVALID_PARENT_ID
                . ') is considered an invalid value',
                __LINE__
            );
            return null;
        }
        $result->parentId = strtolower($parentId);

        $flagsAsString = $parts[3];
        $implicitContextToLog['flagsAsString'] = $flagsAsString;
        if (!IdValidationUtil::isValidHexNumberString($flagsAsString, /* $expectedSizeInBytes */ 1)) {
            $logParsingFailedMessage('flagsAsString is not a valid 2 hex characters string', __LINE__);
            return null;
        }
        $flagsAsInt = hexdec($flagsAsString);
        $result->isSampled = ($flagsAsInt & self::TRACE_PARENT_SAMPLED_FLAG) === 1;

        return $result;
    }

    /**
     * @param string[]               $headerRawValues
     * @param DistributedTracingDataInternal $result
     *
     * @return bool
     */
    private function parseTraceStateHeaders(array $headerRawValues, DistributedTracingDataInternal $result): bool
    {
        /** @var ?string */
        $elasticVendorValue = null;
        /** @var ?string */
        $otherVendorsKeyValuePairs = null;
        if (
            !$this->splitTraceState(
                $headerRawValues,
                $elasticVendorValue /* <- ref */,
                $otherVendorsKeyValuePairs /* <- ref */
            )
        ) {
            return false;
        }

        if (!$this->processSplitTraceState($elasticVendorValue, $otherVendorsKeyValuePairs, /* out */ $result)) {
            return false;
        }

        return true;
    }

    /**
     * @param string[] $headerRawValues
     * @param ?string $elasticVendorValue
     * @param ?string $otherVendorsKeyValuePairs
     *
     * @return bool
     */
    private function splitTraceState(
        array $headerRawValues,
        ?string &$elasticVendorValue,
        ?string &$otherVendorsKeyValuePairs
    ): bool {
        $localLogger = $this->logger->inherit();
        $localLogger->addContext('headerRawValues', $headerRawValues);

        /** @var array<string, string> */
        $encounteredKeyToValue = [];
        $maxAllowedRemainingPairsCount = self::TRACE_STATE_MAX_PAIRS_COUNT;
        foreach ($headerRawValues as $headerRawValue) {
            $keyValuePairs = explode(
                self::TRACE_STATE_KEY_VALUE_PAIRS_SEPARATOR,
                $headerRawValue,
                /* limit: */ $maxAllowedRemainingPairsCount + 1
            );
            foreach ($keyValuePairs as $keyValuePair) {
                $keyValuePair = trim($keyValuePair);
                if (TextUtil::isEmptyString($keyValuePair)) {
                    continue;
                }
                if ($maxAllowedRemainingPairsCount === 0) {
                    ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'Number of found pairs in ' . self::TRACE_STATE_HEADER_NAME . ' HTTP header '
                        . ' is more than allowed maximum (' . self::TRACE_STATE_MAX_PAIRS_COUNT . ')'
                        . ' - only the first ' . self::TRACE_STATE_MAX_PAIRS_COUNT . ' pairs will be used',
                        [
                            'keyValuePair'          => $keyValuePair,
                            'encounteredKeyToValue' => $encounteredKeyToValue,
                            'headerRawValue'        => $headerRawValue,
                        ]
                    );
                    return true;
                }

                /** @var string */
                $key = '';
                /** @var string */
                $value = '';
                if (!$this->splitTraceStateKeyValuePair($keyValuePair, $key, $value)) {
                    return false;
                }

                if (array_key_exists($key, $encounteredKeyToValue)) {
                    ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'Encountered key more than once',
                        [
                            'key'                   => $key,
                            'keyValuePair'          => $keyValuePair,
                            'encounteredKeyToValue' => $encounteredKeyToValue,
                        ]
                    );
                    return false;
                }

                $encounteredKeyToValue[$key] = $value;

                if ($key === self::TRACE_STATE_ELASTIC_VENDOR_KEY) {
                    $elasticVendorValue = $value;
                } else {
                    if ($otherVendorsKeyValuePairs === null) {
                        $otherVendorsKeyValuePairs = '';
                    }
                    self::appendKeyValuePairToTraceState(
                        $key . self::TRACE_STATE_KEY_VALUE_SEPARATOR . $value,
                        $otherVendorsKeyValuePairs /* <- ref */
                    );
                }

                --$maxAllowedRemainingPairsCount;
            }
        }

        return true;
    }

    private static function appendKeyValuePairToTraceState(string $keyValuePair, string &$traceState): void
    {
        if (!TextUtil::isEmptyString($traceState)) {
            $traceState .= self::TRACE_STATE_KEY_VALUE_PAIRS_SEPARATOR;
        }
        $traceState .= $keyValuePair;
    }

    /**
     * @param string $keyValuePair
     * @param string $key
     * @param string $value
     *
     * @return bool
     */
    private function splitTraceStateKeyValuePair(string $keyValuePair, string &$key, string &$value): bool
    {
        if (
            !$this->splitSeparatedPair(
                $keyValuePair,
                self::TRACE_STATE_KEY_VALUE_SEPARATOR /* <- separator */,
                'key' /* dbgFirstPartType */,
                $key /* <- firstPart ref */,
                self::TRACE_STATE_MAX_VENDOR_KEY_LENGTH /* <- firstPartMaxLen */,
                null /* <- firstPartRegex */,
                'value' /* <- dbgSecondPartType */,
                $value /* <- secondPart ref */,
                null /* <- secondPartMaxLen */,
                null /* <- secondPartRegex */
            )
        ) {
            return false;
        }

        if (!$this->validateTraceStateKey($key)) {
            return false;
        }

        return true;
    }

    private function validateTraceStateKey(string $key): bool
    {
        return TextUtil::contains($key, '@')
            ? $this->validateMultiTenantTraceStateKey($key)
            : (preg_match(self::TRACE_STATE_MAX_ID_REGEX, $key) === 1);
    }

    private function validateMultiTenantTraceStateKey(string $key): bool
    {
        /*
         * multi-tenant-key = tenant-id "@" system-id
         * tenant-id = ( lcalpha / DIGIT ) 0*240( lcalpha / DIGIT / "_" / "-"/ "*" / "/" )
         * system-id = lcalpha 0*13( lcalpha / DIGIT / "_" / "-"/ "*" / "/" )
         * lcalpha    = %x61-7A ; a-z
         *
         * The second type of key is used by multi-tenant tracing systems
         * where each tenant requires a unique tracestate entry.
         * Multi-tenant keys consist of a tenant ID followed by the @ character followed by a system ID.
         * This allows for fast and robust parsing. For example, tracing system xyz can easily find all of its
         * tracestate entries by searching for all instances of @xyz=.
         *
         * Identifiers MUST begin with a lowercase letter or a digit, and can only contain lowercase letters (a-z),
         * digits (0-9), underscores (_), dashes (-), asterisks (*), and forward slashes (/).
         *
         * @link https://www.w3.org/TR/trace-context/#key
         */

        $tenantId = '';
        $systemId = '';
        return $this->splitSeparatedPair(
            $key,
            '@' /* <- separator */,
            'tenantId' /* dbgFirstPartType */,
            $tenantId /* <- firstPart ref */,
            self::TRACE_STATE_MAX_TENANT_ID_LENGTH /* <- firstPartMaxLen */,
            self::TRACE_STATE_MAX_ID_REGEX /* <- firstPartRegex */,
            'systemId' /* <- dbgSecondPartType */,
            $systemId /* <- secondPart ref */,
            self::TRACE_STATE_MAX_SYSTEM_ID_LENGTH /* <- secondPartMaxLen */,
            self::TRACE_STATE_MAX_ID_REGEX /* <- secondPartRegex */
        );
    }

    private function processSplitTraceState(
        ?string $elasticVendorValue,
        ?string $otherVendorsKeyValuePairs,
        DistributedTracingDataInternal $result /* <- out */
    ): bool {
        if ($elasticVendorValue !== null) {
            if (!$this->processElasticTraceStateValue($elasticVendorValue, $result)) {
                return false;
            }
        }

        $result->outgoingTraceState
             = $this->buildOutgoingTraceState($elasticVendorValue, $otherVendorsKeyValuePairs);

        return true;
    }

    private function processElasticTraceStateValue(
        string $elasticVendorValue,
        DistributedTracingDataInternal $result
    ): bool {
        /**
         * tracestate: es=s:0.1,othervendor=<opaque>
         *
         * @link https://github.com/elastic/apm/blob/main/specs/agents/tracing-sampling.md#propagation
         */

        $elasticVendorSampleRateSubkey = '';
        $sampleRateAsString = '';

        if (
            !$this->splitSeparatedPair(
                $elasticVendorValue,
                self::TRACE_STATE_ELASTIC_VENDOR_SUBKEY_VALUE_SEPARATOR /* <- separator */,
                'elasticVendorSampleRateSubkey' /* dbgFirstPartType */,
                $elasticVendorSampleRateSubkey /* <- firstPart ref */,
                strlen(self::TRACE_STATE_ELASTIC_VENDOR_SAMPLE_RATE_SUBKEY) /* <- firstPartMaxLen */,
                '/^' . self::TRACE_STATE_ELASTIC_VENDOR_SAMPLE_RATE_SUBKEY . '$/' /* <- firstPartRegex */,
                'sampleRate' /* <- dbgSecondPartType */,
                $sampleRateAsString /* <- secondPart ref */,
                self::TRACE_STATE_ELASTIC_VENDOR_SAMPLE_RATE_VALUE_MAX_LEN /* <- secondPartMaxLen */,
                null /* <- secondPartRegex */
            )
        ) {
            return false;
        }

        $sampleRate = 0.0;
        if (!$this->parseSampleRate($sampleRateAsString, /* ref */ $sampleRate)) {
            return false;
        }

        $result->sampleRate = $sampleRate;
        return true;
    }

    private function parseSampleRate(string $valAsString, float &$parsed): bool
    {
        $sampleRateParser = new FloatOptionParser(/* minValidValue */ 0.0, /* maxValidValue */ 1.0);
        try {
            $parsed = $sampleRateParser->parse($valAsString);
        } catch (ParseException $ex) {
            return false;
        }
        return true;
    }

    public static function convertSampleRateToString(float $sampleRate): string
    {
        $result = number_format(
            $sampleRate,
            4 /* <- number of decimal digits */,
            '.' /* <- decimal_separator */,
            '' /* <- thousands_separator - without thousands separator */
        );
        // Remove trailing zeros if there any
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        $result = preg_replace('/\.?0+$/', '', $result) ?? $result;
        return $result;
    }

    public function buildOutgoingTraceStateForRootTransaction(float $sampleRate): string
    {
        /**
         * tracestate: es=s:0.1,othervendor=<opaque>
         *
         * @link https://github.com/elastic/apm/blob/main/specs/agents/tracing-sampling.md#propagation
         */
        return self::TRACE_STATE_ELASTIC_VENDOR_KEY . self::TRACE_STATE_KEY_VALUE_SEPARATOR
               . self::TRACE_STATE_ELASTIC_VENDOR_SAMPLE_RATE_SUBKEY
               . self::TRACE_STATE_ELASTIC_VENDOR_SUBKEY_VALUE_SEPARATOR
               . self::convertSampleRateToString($sampleRate);
    }

    private function buildOutgoingTraceState(
        ?string $elasticVendorValue,
        ?string $otherVendorsKeyValuePairs
    ): ?string {
        $result = '';

        if ($elasticVendorValue !== null) {
            self::appendKeyValuePairToTraceState(
                self::TRACE_STATE_ELASTIC_VENDOR_KEY . self::TRACE_STATE_KEY_VALUE_SEPARATOR . $elasticVendorValue,
                $result /* <- ref */
            );
        }

        if ($otherVendorsKeyValuePairs !== null) {
            self::appendKeyValuePairToTraceState($otherVendorsKeyValuePairs, /* ref */ $result);
        }

        return TextUtil::isEmptyString($result) ? null : $result;
    }

    public static function buildTraceParentHeader(DistributedTracingData $data): string
    {
        return self::TRACE_PARENT_SUPPORTED_FORMAT_VERSION
               . '-' . $data->traceId
               . '-' . $data->parentId
               . '-' . ($data->isSampled ? '01' : '00');
    }

    /**
     * @param string           $pair
     * @param non-empty-string $separator
     * @param string           $dbgFirstPartType
     * @param string           $firstPart
     * @param ?int             $firstPartMaxLen
     * @param ?string          $firstPartRegex
     * @param string           $dbgSecondPartType
     * @param string           $secondPart
     * @param ?int             $secondPartMaxLen
     * @param ?string          $secondPartRegex
     *
     * @return bool
     */
    private function splitSeparatedPair(
        string $pair,
        string $separator,
        string $dbgFirstPartType,
        string &$firstPart,
        ?int $firstPartMaxLen,
        ?string $firstPartRegex,
        string $dbgSecondPartType,
        string &$secondPart,
        ?int $secondPartMaxLen,
        ?string $secondPartRegex
    ): bool {
        $localLogger = $this->logger->inherit();
        $localLogger->addContext('pair', $pair)->addContext('separator', $separator);

        $dbgPairType = $dbgFirstPartType . $separator . $dbgSecondPartType;
        $parts = explode($separator, $pair, /* limit: */ 3);
        if (count($parts) > 2) {
            ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                $dbgPairType . ' string is invalid because separator appears more than once',
                ['parts' => $parts]
            );
            return false;
        }
        if (count($parts) < 2) {
            ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                $dbgPairType . ' string is invalid because separator (' . $separator . ') is missing',
                ['parts' => $parts]
            );
            return false;
        }

        $dbgPartType = $dbgFirstPartType;
        $maxLen = $firstPartMaxLen;
        $regex = $firstPartRegex;
        foreach ($parts as $part) {
            if (TextUtil::isEmptyString($part)) {
                ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    $dbgPairType . ' string is invalid because ' . $dbgPartType .  ' is empty/whitespace-only string',
                    [$dbgFirstPartType => $parts[0], $dbgSecondPartType => $parts[1]]
                );
                return false;
            }

            if ($maxLen !== null && ($partLen = strlen($part)) > $maxLen) {
                ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    $dbgPairType . ' string is invalid because ' . $dbgPartType .  ' is longer than max allowed',
                    [$dbgPartType . 'length' => $partLen, 'max' => $maxLen, $dbgPartType => $part]
                );
                return false;
            }

            if ($regex !== null && ($pregMatchRetVal = preg_match($regex, $part)) !== 1) {
                ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    $dbgPairType . ' string is invalid because ' . $dbgPartType . ' does not match the pattern',
                    [$dbgPartType => $part, 'regex' => $regex, 'pregMatchRetVal' => $pregMatchRetVal]
                );
                return false;
            }

            $dbgPartType = $dbgSecondPartType;
            $maxLen = $secondPartMaxLen;
            $regex = $secondPartRegex;
        }

        $firstPart = $parts[0];
        $secondPart = $parts[1];
        return true;
    }
}
