<?php

declare(strict_types=1);

namespace ElasticApmTests\Util;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\ErrorData;
use Elastic\Apm\Impl\ErrorExceptionData;
use Elastic\Apm\Impl\ErrorTransactionData;
use Elastic\Apm\Impl\ExecutionSegment;
use Elastic\Apm\Impl\ExecutionSegmentContextData;
use Elastic\Apm\Impl\ExecutionSegmentData;
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\MetadataDiscoverer;
use Elastic\Apm\Impl\NameVersionData;
use Elastic\Apm\Impl\ProcessData;
use Elastic\Apm\Impl\ServiceData;
use Elastic\Apm\Impl\SpanContextData;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\StacktraceFrame;
use Elastic\Apm\Impl\TransactionContextData;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Impl\Util\TextUtil;
use PHPUnit\Framework\TestCase;
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
            ExceptionUtil::buildMessage($msgStart, /* context: */ [], /* numberOfStackFramesToSkip */ 1),
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
        return self::assertValidId($executionSegmentId, Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
    }

    /**
     * @param mixed $errorId
     *
     * @return string
     */
    public static function assertValidErrorId($errorId): string
    {
        return self::assertValidId($errorId, Constants::ERROR_ID_SIZE_IN_BYTES);
    }

    /**
     * @param mixed $traceId
     *
     * @return string
     */
    public static function assertValidTraceId($traceId): string
    {
        return self::assertValidId($traceId, Constants::TRACE_ID_SIZE_IN_BYTES);
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
            self::assertValidKeywordString($key);
            self::assertThat(ExecutionSegment::doesValueHaveSupportedLabelType($value));
            if (is_string($value)) {
                self::assertValidKeywordString($value);
            }
        }
        return $labels;
    }

    public static function assertValidExecutionSegmentData(ExecutionSegmentData $execSegData): void
    {
        self::assertValidDuration($execSegData->duration);
        self::assertValidExecutionSegmentId($execSegData->id);
        self::assertValidKeywordString($execSegData->name);
        self::assertValidTimestamp($execSegData->timestamp);
        self::assertValidTraceId($execSegData->traceId);
        self::assertValidKeywordString($execSegData->type);
    }

    public static function assertValidExecutionSegmentContextData(ExecutionSegmentContextData $execSegCtxData): void
    {
        self::assertValidLabels($execSegCtxData->labels);
    }

    /**
     * @param mixed $droppedSpansCount
     *
     * @return int
     */
    public static function assertValidTransactionDroppedSpansCount($droppedSpansCount): int
    {
        self::assertThat(is_int($droppedSpansCount));

        self::assertThat($droppedSpansCount >= 0);
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

    public static function assertValidTransactionContextData(TransactionContextData $transactionCtxData): void
    {
        self::assertValidExecutionSegmentContextData($transactionCtxData);
    }

    public static function assertValidTransactionData(TransactionData $transaction): void
    {
        self::assertValidExecutionSegmentData($transaction);

        if (!is_null($transaction->parentId)) {
            self::assertValidExecutionSegmentId($transaction->parentId);
        }
        if (!$transaction->isSampled) {
            self::assertThat($transaction->startedSpansCount === 0);
            self::assertThat($transaction->droppedSpansCount === 0);
        }

        if (!is_null($transaction->context)) {
            self::assertValidTransactionContextData($transaction->context);
        }
    }

    /**
     * @param mixed $filename
     *
     * @return string
     */
    public static function assertValidStacktraceFrameFilename($filename): string
    {
        self::assertThat(is_string($filename));
        self::assertThat(!TextUtil::isEmptyString($filename));

        return $filename;
    }

    /**
     * @param mixed $lineNumber
     *
     * @return int
     */
    public static function assertValidStacktraceFrameLineNumber($lineNumber): int
    {
        self::assertThat(is_int($lineNumber));
        self::assertThat($lineNumber >= 0);

        return $lineNumber;
    }

    /**
     * @param mixed $function
     *
     * @return string|null
     */
    public static function assertValidStacktraceFrameFunction($function): ?string
    {
        if (!is_null($function)) {
            self::assertThat(is_string($function));
            self::assertThat(!TextUtil::isEmptyString($function));
        }

        return $function;
    }

    public static function assertValidStacktraceFrame(StacktraceFrame $stacktraceFrame): void
    {
        self::assertValidStacktraceFrameFilename($stacktraceFrame->filename);
        self::assertValidStacktraceFrameLineNumber($stacktraceFrame->lineno);
        self::assertValidStacktraceFrameFunction($stacktraceFrame->function);
    }

    /**
     * @param StacktraceFrame[] $stacktrace
     */
    public static function assertValidStacktrace(array $stacktrace): void
    {
        foreach ($stacktrace as $stacktraceFrame) {
            self::assertValidStacktraceFrame($stacktraceFrame);
        }
    }

    public static function assertValidSpanContextData(SpanContextData $spanCtxData): void
    {
        self::assertValidExecutionSegmentContextData($spanCtxData);
    }

    public static function assertValidSpanData(SpanData $span): void
    {
        self::assertValidExecutionSegmentData($span);

        self::assertValidNullableKeywordString($span->action);
        self::assertValidExecutionSegmentId($span->parentId);
        if (!is_null($span->stacktrace)) {
            self::assertValidStacktrace($span->stacktrace);
        }
        self::assertValidNullableKeywordString($span->subtype);

        if (!is_null($span->context)) {
            self::assertValidSpanContextData($span->context);
        }
    }

    public static function assertValidErrorTransactionData(ErrorTransactionData $errorTransactionData): void
    {
        self::assertValidKeywordString($errorTransactionData->type);
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public static function assertValidErrorExceptionCode($value)
    {
        if (is_int($value)) {
            return $value;
        }

        return self::assertValidNullableKeywordString($value);
    }

    public static function assertValidErrorExceptionData(ErrorExceptionData $errorExceptionData): void
    {
        self::assertValidErrorExceptionCode($errorExceptionData->code);
        self::assertValidNullableNonKeywordString($errorExceptionData->message);
        self::assertValidNullableKeywordString($errorExceptionData->module);
        if (!is_null($errorExceptionData->stacktrace)) {
            self::assertValidStacktrace($errorExceptionData->stacktrace);
        }
        self::assertValidNullableKeywordString($errorExceptionData->type);
    }

    public static function assertValidErrorData(ErrorData $error): void
    {
        ValidationUtil::assertValidTimestamp($error->timestamp);
        ValidationUtil::assertValidErrorId($error->id);

        ValidationUtil::assertThat(is_null($error->traceId) === is_null($error->transactionId));
        ValidationUtil::assertThat(is_null($error->traceId) === is_null($error->parentId));
        ValidationUtil::assertThat(is_null($error->traceId) === is_null($error->transaction));

        if (!is_null($error->traceId)) {
            ValidationUtil::assertValidTraceId($error->traceId);
        }
        if (!is_null($error->transactionId)) {
            ValidationUtil::assertValidExecutionSegmentId($error->transactionId);
        }
        if (!is_null($error->parentId)) {
            ValidationUtil::assertValidExecutionSegmentId($error->parentId);
        }
        if (!is_null($error->transaction)) {
            ValidationUtil::assertValidErrorTransactionData($error->transaction);
        }

        if (!is_null($error->context)) {
            ValidationUtil::assertValidTransactionContextData($error->context);
        }

        if (!is_null($error->exception)) {
            ValidationUtil::assertValidErrorExceptionData($error->exception);
        }
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
        self::assertValidProcessId($processData->pid);
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
        self::assertValidNullableKeywordString($nameVersionData->name);
        self::assertValidNullableKeywordString($nameVersionData->version);
    }

    public static function assertValidServiceData(ServiceData $serviceData): void
    {
        self::assertValidServiceName($serviceData->name);
        self::assertValidNullableKeywordString($serviceData->version);
        self::assertValidNullableKeywordString($serviceData->environment);

        self::assertValidNameVersionData($serviceData->agent);
        TestCase::assertNotNull($serviceData->agent);
        self::assertThat($serviceData->agent->name === MetadataDiscoverer::AGENT_NAME);
        self::assertThat($serviceData->agent->version === ElasticApm::VERSION);
        self::assertValidNullableKeywordString($serviceData->agent->ephemeralId);

        self::assertValidNameVersionData($serviceData->framework);

        self::assertValidNameVersionData($serviceData->language);
        TestCase::assertNotNull($serviceData->language);
        self::assertThat($serviceData->language->name === MetadataDiscoverer::LANGUAGE_NAME);

        self::assertValidNameVersionData($serviceData->runtime);
        TestCase::assertNotNull($serviceData->runtime);
        self::assertThat($serviceData->runtime->name === MetadataDiscoverer::LANGUAGE_NAME);
        self::assertThat($serviceData->runtime->version === $serviceData->language->version);
    }

    public static function assertValidMetadata(Metadata $metadata): void
    {
        self::assertValidServiceData($metadata->service);
        self::assertValidProcessData($metadata->process);
    }

    /**
     * @param mixed $boolVal
     *
     * @return bool
     */
    public static function assertValidBool($boolVal): bool
    {
        self::assertThat(is_bool($boolVal));

        return $boolVal;
    }
}
