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
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Util\IdValidationUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;

final class HttpDistributedTracing
{
    use StaticClassTrait;

    public const TRACE_PARENT_HEADER_NAME = 'traceparent';
    private const TRACE_PARENT_SUPPORTED_FORMAT_VERSION = '00';
    public const TRACE_PARENT_INVALID_TRACE_ID = '00000000000000000000000000000000';
    public const TRACE_PARENT_INVALID_PARENT_ID = '0000000000000000';
    private const TRACE_PARENT_SAMPLED_FLAG = 0b00000001;

    public const TRACE_STATE_HEADER_NAME = 'tracestate';
    public const TRACE_STATE_MAX_PAIRS_COUNT = 32;
    // public const TRACE_STATE_ELASTIC_VENDOR_KEY = 'es';

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
     * @param array<string> $traceParentHeaders
     * @param array<string> $traceStateHeaders
     *
     * @return ?DistributedTracingData
     */
    public function parseHeaders(array $traceParentHeaders, array $traceStateHeaders): ?DistributedTracingData
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
     * @param array<string> $traceParentHeaders
     * @param array<string> $traceStateHeaders
     * @param bool          $isTraceParentValid
     * @param bool|null     $isTraceStateValid
     *
     * @return ?DistributedTracingData
     */
    public function parseHeadersImpl(
        array $traceParentHeaders,
        array $traceStateHeaders,
        bool &$isTraceParentValid,
        ?bool &$isTraceStateValid
    ): ?DistributedTracingData {
        if (count($traceParentHeaders) !== 1) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Found more than one ' . self::TRACE_PARENT_HEADER_NAME . ' HTTP header which is invalid',
                ['traceParentHeaders' => $traceParentHeaders]
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

        $isTraceParentValid = ($result = $this->parseTraceParentHeader($traceParentHeaders[0])) !== null;
        if (!$isTraceParentValid) {
            $isTraceStateValid = null;
            return null;
        }

        $this->parseTraceStateHeaders($traceStateHeaders, $result, /* ref */ $isTraceStateValid);
        return $result;
    }

    public function parseTraceParentHeader(string $headerRawValue): ?DistributedTracingData
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

        $result = new DistributedTracingData();

        $expectedNumberOfParts = 4;
        $implicitContextToLog['expectedNumberOfParts'] = $expectedNumberOfParts;
        /**
         * limit is $expectedNumberOfParts + 1 because according to W3C spec requires parser to allow
         * more than $expectedNumberOfParts in the future versions
         * as long as the new parts are appended at the end after a dash
         *
         * @link https://www.w3.org/TR/trace-context/#versioning-of-traceparent
         */
        $parts = explode(/* delimiter: */ '-', $headerValue, /* limit: */ $expectedNumberOfParts + 1);
        $implicitContextToLog['parts'] = $parts;
        if (count($parts) < $expectedNumberOfParts) {
            $logParsingFailedMessage("the number of delimited parts is less than expected", __LINE__);
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
                'there are more than expected number of delimited parts for the current format version',
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
     * @param array<string>          $headerRawValues
     * @param DistributedTracingData $result
     * @param ?bool                  $isTraceStateValid
     *
     * @return void
     */
    private function parseTraceStateHeaders(
        array $headerRawValues,
        DistributedTracingData $result,
        ?bool &$isTraceStateValid
    ): void {
        $localLogger = $this->logger->inherit();
        $localLogger->addContext('headerRawValues', $headerRawValues);

        /** @var array<string> */
        $pairs = [];
        $maxAllowedRemainingPairsCount = self::TRACE_STATE_MAX_PAIRS_COUNT;
        foreach ($headerRawValues as $headerRawValue) {
            $currentPairs = explode(',', $headerRawValue, /* limit: */ $maxAllowedRemainingPairsCount + 1);
            if (count($currentPairs) > $maxAllowedRemainingPairsCount) {
                ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Number of found pairs in ' . self::TRACE_STATE_HEADER_NAME . ' HTTP header '
                    . ' is more than allowed maximum (' . self::TRACE_STATE_MAX_PAIRS_COUNT . ')'
                    . ' - only the first ' . self::TRACE_STATE_MAX_PAIRS_COUNT . ' pairs will be kept',
                    [
                        'maxAllowedRemainingPairsCount' => $maxAllowedRemainingPairsCount,
                        'currentPairs'                  => $currentPairs,
                    ]
                );
            }
            $pairs = array_merge($pairs, $currentPairs);
            $maxAllowedRemainingPairsCount -= count($currentPairs);
            if ($maxAllowedRemainingPairsCount <= 0) {
                break;
            }
        }

        $this->parseTraceStatePairs($pairs, $result, /* ref */ $isTraceStateValid);
    }

    /**
     * @param array<string>          $pairs
     * @param DistributedTracingData $result
     * @param ?bool                  $isTraceStateValid
     *
     * @return void
     */
    private function parseTraceStatePairs(
        array $pairs,
        DistributedTracingData $result,
        ?bool &$isTraceStateValid
    ): void {
        // TODO: Sergey Kleyman: Implement: HttpDistributedTracing::buildTraceStateHeader
        // $localLogger = $this->logger->inherit();
        // $localLogger->addContext('pairs', $pairs);
        //
        // /** @var array<string> */
        // $stateOtherVendors = [];
        // foreach ($pairs as $pair) {
        //     if (ctype_space($pair)) {
        //         continue;
        //     }
        //
        //     $keyValueArr = explode(/* separator: */ '=', $pair, /* limit: */ 2);
        //     if (count($keyValueArr) ) {
        //         ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        //         && $loggerProxy->log(
        //             'Number of found pairs in ' . self::TRACE_STATE_HEADER_NAME . ' HTTP header '
        //             . ' is more than allowed maximum (' . self::TRACE_STATE_MAX_PAIRS_COUNT . ')'
        //             . ' - only the first ' . self::TRACE_STATE_MAX_PAIRS_COUNT . ' pairs will be kept',
        //             [
        //                 'maxAllowedRemainingPairsCount' => $maxAllowedRemainingPairsCount,
        //                 'currentPairs'                  => $currentPairs,
        //             ]
        //         );
        //     }
        // }
        // self::TRACE_STATE_ELASTIC_VENDOR_KEY
    }

    public static function buildTraceParentHeader(DistributedTracingData $data): string
    {
        return self::TRACE_PARENT_SUPPORTED_FORMAT_VERSION
               . '-' . $data->traceId
               . '-' . $data->parentId
               . '-' . ($data->isSampled ? '01' : '00');
    }

    // public static function buildTraceStateHeader(DistributedTracingData $data): string
    // {
    //     // TODO: Sergey Kleyman: Implement: HttpDistributedTracing::buildTraceStateHeader
    //     return '';
    // }
}
