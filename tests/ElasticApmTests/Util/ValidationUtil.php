<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\ExecutionSegmentContextInterface;
use Elastic\Apm\ExecutionSegmentInterface;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\ExecutionSegmentContext;
use Elastic\Apm\Impl\MetadataDiscovery;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Metadata;
use Elastic\Apm\NameVersionData;
use Elastic\Apm\ProcessData;
use Elastic\Apm\ServiceData;
use Elastic\Apm\SpanContextInterface;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionContextInterface;
use Elastic\Apm\TransactionInterface;
use Throwable;

final class ValidationUtil
{
    use StaticClassTrait;

    /** @var float Timestamp for February 20, 2020 18:04:47.987 */
    private const PAST_TIMESTAMP = 1582221887987;

    public static function buildException(
        ?string $msgDetails = null,
        int $code = 0,
        Throwable $previous = null
    ): InvalidEventDataException {
        $msgStart = 'Validation failed';
        if (!is_null($msgDetails)) {
            $msgStart .= ': ';
            $msgStart .= $msgDetails;
        }

        return new InvalidEventDataException(
            ExceptionUtil::buildMessageWithStacktrace($msgStart, /* numberOfStackFramesToSkip */ 1),
            $code,
            $previous
        );
    }

    public static function assertThat(bool $condition): void
    {
        if ($condition) {
            return;
        }

        throw self::buildException();
    }

    /**
     * @param mixed $id
     * @param int   $expectedSizeInBytes
     *
     * @return string
     */
    public static function assertValidId($id, int $expectedSizeInBytes): string
    {
        self::assertThat(is_string($id));

        self::assertThat($expectedSizeInBytes * 2 === strlen($id));

        foreach (str_split($id) as $idChar) {
            self::assertThat(ctype_xdigit($idChar));
        }

        return $id;
    }

    /**
     * @param mixed $executionSegmentId
     *
     * @return string
     */
    public static function assertValidExecutionSegmentId($executionSegmentId): string
    {
        return self::assertValidId($executionSegmentId, IdGenerator::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
    }

    /**
     * @param mixed $traceId
     *
     * @return string
     */
    public static function assertValidTraceId($traceId): string
    {
        return self::assertValidId($traceId, IdGenerator::TRACE_ID_SIZE_IN_BYTES);
    }

    /**
     * @param mixed $stringValue
     * @param bool  $isNullable
     * @param int   $maxLength
     *
     * @return mixed
     */
    public static function assertValidString($stringValue, bool $isNullable, int $maxLength)
    {
        if (is_null($stringValue)) {
            self::assertThat($isNullable);
            return null;
        }

        self::assertThat(is_string($stringValue));

        self::assertThat(strlen($stringValue) <= $maxLength);
        return $stringValue;
    }

    /**
     * @param mixed $keywordString
     *
     * @return string
     */
    public static function assertValidNullableKeywordString($keywordString): ?string
    {
        return self::assertValidString($keywordString, /* isNullable: */ true, Constants::KEYWORD_STRING_MAX_LENGTH);
    }

    /**
     * @param mixed $keywordString
     *
     * @return string
     */
    public static function assertValidKeywordString($keywordString): string
    {
        return self::assertValidString($keywordString, /* isNullable: */ false, Constants::KEYWORD_STRING_MAX_LENGTH);
    }

    /**
     * @param mixed $nonKeywordString
     *
     * @return string
     */
    public static function assertValidNullableNonKeywordString($nonKeywordString): ?string
    {
        return self::assertValidString(
            $nonKeywordString,
            /* isNullable: */ true,
            Constants::NON_KEYWORD_STRING_MAX_LENGTH
        );
    }

    /**
     * @param mixed $timestamp
     *
     * @return float
     */
    public static function assertValidTimestamp($timestamp): float
    {
        self::assertThat(is_float($timestamp) || is_int($timestamp));

        self::assertThat($timestamp >= self::PAST_TIMESTAMP);
        return $timestamp;
    }

    /**
     * @param mixed $duration
     *
     * @return float
     */
    public static function assertValidDuration($duration): float
    {
        self::assertThat(is_float($duration) || is_int($duration));

        self::assertThat($duration >= 0);
        return $duration;
    }

    /**
     * @param array<string, string|bool|int|float|null> $labels
     *
     * @return array<string, string|bool|int|float|null>
     */
    public static function assertValidLabels(array $labels): array
    {
        foreach ($labels as $key => $value) {
            self::assertThat(is_string($key));
            self::assertThat(ExecutionSegmentContext::doesValueHaveSupportedLabelType($value));
            if (is_string($value)) {
                self::assertValidKeywordString($value);
            }
        }
        return $labels;
    }

    public static function assertValidExecutionSegmentContext(ExecutionSegmentContextInterface $execSegmentCtx): void
    {
        self::assertValidLabels($execSegmentCtx->getLabels());
    }

    public static function assertValidSpanContext(SpanContextInterface $spanContext): void
    {
        self::assertValidExecutionSegmentContext($spanContext);
    }

    public static function assertValidTransactionContext(TransactionContextInterface $transactionContext): void
    {
        self::assertValidExecutionSegmentContext($transactionContext);
    }

    public static function assertValidExecutionSegment(ExecutionSegmentInterface $executionSegment): void
    {
        self::assertThat($executionSegment->hasEnded());
        self::assertValidDuration($executionSegment->getDuration());
        self::assertValidExecutionSegmentId($executionSegment->getId());
        self::assertValidKeywordString($executionSegment->getName());
        self::assertValidTimestamp($executionSegment->getTimestamp());
        self::assertValidTraceId($executionSegment->getTraceId());
        self::assertValidKeywordString($executionSegment->getType());
    }

    /**
     * @param mixed $droppedSpansCount
     *
     * @return int
     */
    public static function assertValidTransactionDroppedSpansCount($droppedSpansCount): int
    {
        self::assertThat(is_int($droppedSpansCount));

        // Until we support dropped spans the count should be zero
        // self::assertThat($droppedSpansCount >= 0);
        self::assertThat($droppedSpansCount == 0);
        return $droppedSpansCount;
    }

    /**
     * @param mixed $startedSpansCount
     *
     * @return int
     */
    public static function assertValidTransactionStartedSpansCount($startedSpansCount): int
    {
        self::assertThat(is_int($startedSpansCount));

        self::assertThat($startedSpansCount >= 0);
        return $startedSpansCount;
    }

    public static function assertValidTransaction(TransactionInterface $transaction): void
    {
        self::assertValidExecutionSegment($transaction);
        self::assertValidTransactionContext($transaction->context());
        if (!is_null($transaction->getParentId())) {
            self::assertValidExecutionSegmentId($transaction->getParentId());
        }
        self::assertValidTransactionDroppedSpansCount($transaction->getDroppedSpansCount());
        self::assertValidTransactionStartedSpansCount($transaction->getStartedSpansCount());
    }

    /**
     * @param mixed $start
     *
     * @return float
     */
    public static function assertValidSpanStart($start): float
    {
        return self::assertValidDuration($start);
    }

    public static function assertValidSpan(SpanInterface $span): void
    {
        self::assertValidExecutionSegment($span);
        self::assertValidNullableKeywordString($span->getAction());
        self::assertValidSpanContext($span->context());
        self::assertValidExecutionSegmentId($span->getParentId());
        self::assertValidSpanStart($span->getStart());
        self::assertValidNullableKeywordString($span->getSubtype());
    }

    /**
     * @param mixed $pid
     *
     * @return int
     */
    public static function assertValidProcessId($pid): int
    {
        self::assertThat(is_int($pid));

        self::assertThat($pid > 0);

        return $pid;
    }

    public static function assertValidProcessData(ProcessData $processData): void
    {
        self::assertValidProcessId($processData->getPid());
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    public static function assertValidServiceName($value): string
    {
        self::assertValidNullableKeywordString($value);

        return $value;
    }

    public static function assertValidNameVersionData(?NameVersionData $nameVersionData): void
    {
        if (is_null($nameVersionData)) {
            return;
        }
        self::assertValidNullableKeywordString($nameVersionData->getName());
        self::assertValidNullableKeywordString($nameVersionData->getVersion());
    }

    public static function assertValidServiceData(ServiceData $serviceData): void
    {
        self::assertValidServiceName($serviceData->getName());
        self::assertValidNullableKeywordString($serviceData->getVersion());
        self::assertValidNullableKeywordString($serviceData->getEnvironment());

        self::assertValidNameVersionData($serviceData->agent());
        self::assertThat($serviceData->agent()->getName() === ServiceData::DEFAULT_AGENT_NAME);
        self::assertThat($serviceData->agent()->getVersion() === ElasticApm::VERSION);

        self::assertValidNameVersionData($serviceData->framework());

        self::assertValidNameVersionData($serviceData->language());
        self::assertThat($serviceData->language()->getName() === ServiceData::DEFAULT_LANGUAGE_NAME);

        self::assertValidNameVersionData($serviceData->runtime());
        self::assertThat($serviceData->runtime()->getName() === $serviceData->language()->getName());
        self::assertThat($serviceData->runtime()->getVersion() === $serviceData->language()->getVersion());
    }

    public static function assertValidMetadata(Metadata $metadata): void
    {
        self::assertValidServiceData($metadata->service());
        self::assertValidProcessData($metadata->process());
    }
}
