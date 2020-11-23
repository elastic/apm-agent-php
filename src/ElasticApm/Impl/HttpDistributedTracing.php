<?php

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

    private const SUPPORTED_FORMAT_VERSION = '00';

    public const INVALID_TRACE_ID = '00000000000000000000000000000000';
    public const INVALID_PARENT_ID = '0000000000000000';
    private const SAMPLED_FLAG = 0b00000001;

    public const UBER_TRACE_ID_HEADER_NAME = 'uber-trace-id';

    private const UBER_64_BIT_TRACE_ID_SIZE_IN_BYTES = 8;

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

    public function parseTraceParentHeader(string $headerValue): ?DistributedTracingData
    {
        // 00-0af7651916cd43dd8448eb211c80319c-b9c7c989f97918e1-01
        // ^^ ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^ ^^^^^^^^^^^^^^^^ ^^
        // || |||||||||||||||||||||||||||||||| |||||||||||||||| -- - flagsAsString
        // || |||||||||||||||||||||||||||||||| ---------------- - parentId
        // || -------------------------------- - trace-id
        // -- - version

        $parentFunc = __FUNCTION__;
        $logParsingFailedMessage = function (
            string $reason,
            array $context,
            int $srcCodeLineNumber
        ) use (
            $parentFunc,
            $headerValue
        ): void {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled($srcCodeLineNumber, $parentFunc))
            && $loggerProxy->log(
                "Failed to parse HTTP header used for distributed tracing: $reason",
                array_merge($context, ['headerValue' => $headerValue])
            );
        };

        $result = new DistributedTracingData();

        $expectedNumberOfParts = 4;
        $parts = explode(/* delimiter: */ '-', $headerValue, /* limit: */ $expectedNumberOfParts);
        if (count($parts) < $expectedNumberOfParts) {
            $logParsingFailedMessage(
                "there are less than $expectedNumberOfParts delimited parts",
                ['parts' => $parts],
                __LINE__
            );
            return null;
        }

        $version = $parts[0];
        if ($version !== self::SUPPORTED_FORMAT_VERSION) {
            $logParsingFailedMessage('unsupported version', ['version' => $version, 'parts' => $parts], __LINE__);
            return null;
        }

        $traceId = $parts[1];
        if (!IdValidationUtil::isValidHexNumberString($traceId, Constants::TRACE_ID_SIZE_IN_BYTES)) {
            $logParsingFailedMessage(
                'traceId is not a valid ' . Constants::TRACE_ID_SIZE_IN_BYTES . ' bytes hex ID',
                ['traceId' => $traceId, 'parts' => $parts],
                __LINE__
            );
            return null;
        }
        if ($traceId === self::INVALID_TRACE_ID) {
            $logParsingFailedMessage(
                'traceId that is all bytes as zero (00000000000000000000000000000000) is considered an invalid value',
                ['traceId' => $traceId, 'parts' => $parts],
                __LINE__
            );
            return null;
        }
        $result->traceId = strtolower($traceId);

        $parentId = $parts[2];
        if (!IdValidationUtil::isValidHexNumberString($parentId, Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES)) {
            $logParsingFailedMessage(
                'parentId is not a valid ' . Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES . ' bytes hex ID',
                ['parentId' => $parentId, 'parts' => $parts],
                __LINE__
            );
            return null;
        }
        if ($parentId === self::INVALID_PARENT_ID) {
            $logParsingFailedMessage(
                'parentId that is all bytes as zero (0000000000000000) is considered an invalid value',
                ['parentId' => $parentId, 'parts' => $parts],
                __LINE__
            );
            return null;
        }
        $result->parentId = strtolower($parentId);

        $flagsAsString = $parts[3];
        if (!IdValidationUtil::isValidHexNumberString($flagsAsString, /* $expectedSizeInBytes */ 1)) {
            $logParsingFailedMessage(
                'flagsAsString is not a valid 1 byte hex number',
                ['$flagsAsString' => $flagsAsString, 'parts' => $parts],
                __LINE__
            );
            return null;
        }
        $flagsAsInt = hexdec($flagsAsString);
        $result->isSampled = ($flagsAsInt & self::SAMPLED_FLAG) === 1;

        return $result;
    }

    public function parseUberTraceIdHeader(string $headerValue): ?DistributedTracingData
    {
        // 8448eb211c80319c:b9c7c989f97918e1:0af7651916cd43dd:1
        // ^^^^^^^^^^^^^^^^ ^^^^^^^^^^^^^^^^ ^^^^^^^^^^^^^^^^ ^
        // |||||||||||||||| |||||||||||||||| |||||||||||||||| - - flagsAsString
        // |||||||||||||||| |||||||||||||||| ---------------- - parent-span-id (deprecated and ignored)
        // |||||||||||||||| ---------------- - span-id
        // ---------------- - trace-id

        $parentFunc = __FUNCTION__;
        $logParsingFailedMessage = function (
            string $reason,
            array $context,
            int $srcCodeLineNumber
        ) use (
            $parentFunc,
            $headerValue
        ): void {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled($srcCodeLineNumber, $parentFunc))
            && $loggerProxy->log(
                "Failed to parse HTTP header used for distributed tracing: $reason",
                array_merge($context, ['headerValue' => $headerValue])
            );
        };

        $result = new DistributedTracingData();

        $expectedNumberOfParts = 4;
        $parts = explode(/* delimiter: */ ':', $headerValue, /* limit: */ $expectedNumberOfParts);
        if (count($parts) < $expectedNumberOfParts) {
            $logParsingFailedMessage(
                "there are less than $expectedNumberOfParts delimited parts",
                ['parts' => $parts],
                __LINE__
            );
            return null;
        }

        $traceId = $parts[0];
        if (
            !IdValidationUtil::isValidHexNumberString($traceId, self::UBER_64_BIT_TRACE_ID_SIZE_IN_BYTES) &&
            !IdValidationUtil::isValidHexNumberString($traceId, Constants::TRACE_ID_SIZE_IN_BYTES)
        ) {
            $logParsingFailedMessage(
                'traceId is not a valid ' . self::UBER_64_BIT_TRACE_ID_SIZE_IN_BYTES . ' or ' . Constants::TRACE_ID_SIZE_IN_BYTES . ' bytes hex ID',
                ['traceId' => $traceId, 'parts' => $parts],
                __LINE__
            );
            return null;
        }
        if (str_pad($traceId, 2 * Constants::TRACE_ID_SIZE_IN_BYTES, '0', STR_PAD_LEFT)  === self::INVALID_TRACE_ID) {
            $logParsingFailedMessage(
                'traceId that is all bytes as zero (0000000000000000 or 00000000000000000000000000000000) is considered an invalid value',
                ['traceId' => $traceId, 'parts' => $parts],
                __LINE__
            );
            return null;
        }
        $result->traceId = strtolower($traceId);

        $spanId = $parts[1];
        if (!IdValidationUtil::isValidHexNumberString($spanId, Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES)) {
            $logParsingFailedMessage(
                'spanId is not a valid ' . Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES . ' bytes hex ID',
                ['spanId' => $spanId, 'parts' => $parts],
                __LINE__
            );
            return null;
        }
        if ($spanId === self::INVALID_PARENT_ID) {
            $logParsingFailedMessage(
                'spanId that is all bytes as zero (0000000000000000) is considered an invalid value',
                ['spanId' => $spanId, 'parts' => $parts],
                __LINE__
            );
            return null;
        }
        $result->parentId = strtolower($spanId);

        $flagsAsString = str_pad($parts[3], 2, '0', STR_PAD_LEFT);
        if (!IdValidationUtil::isValidHexNumberString($flagsAsString, /* $expectedSizeInBytes */ 1)) {
            $logParsingFailedMessage(
                'flagsAsString is not a valid 1 byte hex number',
                ['$flagsAsString' => $flagsAsString, 'parts' => $parts],
                __LINE__
            );
            return null;
        }
        $flagsAsInt = hexdec($flagsAsString);
        $result->isSampled = ($flagsAsInt & self::SAMPLED_FLAG) === 1;

        return $result;
    }

    public function buildTraceParentHeader(DistributedTracingData $data): string
    {
        return self::SUPPORTED_FORMAT_VERSION
           . '-' . $data->traceId
           . '-' . $data->parentId
           . '-' . ($data->isSampled ? '01' : '00');
    }
}
