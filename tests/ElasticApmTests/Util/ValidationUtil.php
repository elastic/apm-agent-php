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

namespace ElasticApmTests\Util;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\ErrorData;
use Elastic\Apm\Impl\ErrorExceptionData;
use Elastic\Apm\Impl\ErrorTransactionData;
use Elastic\Apm\Impl\ExecutionSegment;
use Elastic\Apm\Impl\ExecutionSegmentContext;
use Elastic\Apm\Impl\ExecutionSegmentContextData;
use Elastic\Apm\Impl\ExecutionSegmentData;
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\MetadataDiscoverer;
use Elastic\Apm\Impl\MetricSetData;
use Elastic\Apm\Impl\NameVersionData;
use Elastic\Apm\Impl\ProcessData;
use Elastic\Apm\Impl\ServiceData;
use Elastic\Apm\Impl\SpanContextData;
use Elastic\Apm\Impl\SpanContextDbData;
use Elastic\Apm\Impl\SpanContextDestinationData;
use Elastic\Apm\Impl\SpanContextDestinationServiceData;
use Elastic\Apm\Impl\SpanContextHttpData;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\StacktraceFrame;
use Elastic\Apm\Impl\SystemData;
use Elastic\Apm\Impl\TransactionContextData;
use Elastic\Apm\Impl\TransactionContextRequestData;
use Elastic\Apm\Impl\TransactionContextRequestUrlData;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\BoolUtil;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\IdValidationUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Impl\Util\TextUtil;
use Throwable;

final class ValidationUtil
{
    use StaticClassTrait;

    // Timestamp for February 20, 2020 18:04:47.987
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
            ExceptionUtil::buildMessage($msgStart, /* context: */ [], /* numberOfStackFramesToSkip */ 2),
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
        /** @var string $id */
        self::assertThat(IdValidationUtil::isValidHexNumberString($id, $expectedSizeInBytes));
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
     * @return ?string
     */
    public static function assertValidString($stringValue, bool $isNullable, int $maxLength): ?string
    {
        if ($stringValue === null) {
            self::assertThat($isNullable);
            return null;
        }

        self::assertThat(is_string($stringValue));
        /** @var string $stringValue */

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
        $value = self::assertValidString($keywordString, /* isNullable: */ false, Constants::KEYWORD_STRING_MAX_LENGTH);
        /** @var string $value */
        return $value;
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
        /** @var float|int $timestamp */

        self::assertThat($timestamp >= self::PAST_TIMESTAMP);
        return floatval($timestamp);
    }

    /**
     * @param mixed $duration
     *
     * @return float
     */
    public static function assertValidDuration($duration): float
    {
        self::assertThat(is_float($duration) || is_int($duration));
        /** @var float|int $duration */

        self::assertThat($duration >= 0);
        return floatval($duration);
    }

    /**
     * @param mixed $labels
     *
     * @return array<string, string|bool|int|float|null>
     */
    public static function assertValidLabels($labels): array
    {
        self::assertThat(is_array($labels));
        /** @var array<mixed, mixed> $labels */
        foreach ($labels as $key => $value) {
            self::assertValidKeywordString($key);
            self::assertThat(ExecutionSegmentContext::doesValueHaveSupportedLabelType($value));
            if (is_string($value)) {
                self::assertValidKeywordString($value);
            }
        }
        /** @var array<string, string|bool|int|float|null> $labels */
        return $labels;
    }

    /**
     * @param mixed $outcome
     *
     * @return ?string
     */
    public static function assertValidOutcome($outcome): ?string
    {
        self::assertThat($outcome === null || is_string($outcome));
        /** @var ?string $outcome */
        self::assertThat(ExecutionSegment::isValidOutcome($outcome));
        return $outcome;
    }

    /**
     * @param mixed $value
     *
     * @return float|null
     */
    public static function assertValidSampleRate($value)
    {
        if ($value === null) {
            return null;
        }
        self::assertThat(is_float($value) || is_int($value));
        self::assertThat(0 <= $value && $value <= 1);
        return floatval($value);
    }

    public static function assertValidExecutionSegmentData(ExecutionSegmentData $execSegData): void
    {
        self::assertValidDuration($execSegData->duration);
        self::assertValidExecutionSegmentId($execSegData->id);
        self::assertValidKeywordString($execSegData->name);
        self::assertValidTimestamp($execSegData->timestamp);
        self::assertValidTraceId($execSegData->traceId);
        self::assertValidKeywordString($execSegData->type);
        self::assertValidOutcome($execSegData->outcome);
        self::assertValidSampleRate($execSegData->sampleRate);
    }

    public static function assertValidExecutionSegmentContextData(ExecutionSegmentContextData $execSegCtxData): void
    {
        self::assertValidLabels($execSegCtxData->labels);
    }

    /**
     * @param mixed $count
     *
     * @return int
     */
    public static function assertValidCount($count): int
    {
        self::assertThat(is_int($count));
        /** @var int $count */
        self::assertThat($count >= 0);
        return $count;
    }

    public static function assertValidTransactionContextRequestUrlData(
        TransactionContextRequestUrlData $transactionContextRequestUrlData
    ): void {
        self::assertValidNullableKeywordString($transactionContextRequestUrlData->domain);
        self::assertValidNullableKeywordString($transactionContextRequestUrlData->full);
        self::assertValidNullableKeywordString($transactionContextRequestUrlData->original);
        self::assertValidNullableKeywordString($transactionContextRequestUrlData->path);
        self::assertValidNullablePort($transactionContextRequestUrlData->port);
        self::assertValidNullableKeywordString($transactionContextRequestUrlData->protocol);
        self::assertValidNullableKeywordString($transactionContextRequestUrlData->query);
    }

    public static function assertValidTransactionContextRequestData(
        TransactionContextRequestData $transactionContextRequestData
    ): void {
        if ($transactionContextRequestData->url !== null) {
            self::assertValidTransactionContextRequestUrlData($transactionContextRequestData->url);
        }
    }

    public static function assertValidTransactionContextData(TransactionContextData $transactionContextData): void
    {
        self::assertValidExecutionSegmentContextData($transactionContextData);
        if ($transactionContextData->request !== null) {
            self::assertValidTransactionContextRequestData($transactionContextData->request);
        }
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
        /** @var string $filename */
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
        /** @var int $lineNumber */
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
            /** @var string $function */
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

    /**
     * @param mixed $value
     *
     * @return int|null
     */
    public static function assertValidNullableHttpStatusCode($value): ?int
    {
        if (is_null($value)) {
            return null;
        }

        self::assertThat(is_int($value));
        assert(is_int($value));
        return $value;
    }

    /**
     * @param mixed $value
     *
     * @return int|null
     */
    public static function assertValidNullablePort($value): ?int
    {
        if (is_null($value)) {
            return null;
        }

        self::assertThat(is_int($value));
        assert(is_int($value));
        return $value;
    }

    public static function assertValidSpanContextDbData(SpanContextDbData $obj): void
    {
        self::assertValidNullableNonKeywordString($obj->statement);
    }

    public static function assertValidSpanContextHttpData(SpanContextHttpData $obj): void
    {
        self::assertValidNullableNonKeywordString($obj->url);
        self::assertValidNullableHttpStatusCode($obj->statusCode);
        self::assertValidNullableKeywordString($obj->method);
    }

    public static function assertValidSpanContextDestinationServiceData(SpanContextDestinationServiceData $obj): void
    {
        self::assertValidKeywordString($obj->name);
        self::assertValidKeywordString($obj->resource);
        self::assertValidKeywordString($obj->type);
    }

    public static function assertValidSpanContextDestinationData(SpanContextDestinationData $obj): void
    {
        if ($obj->service !== null) {
            self::assertValidSpanContextDestinationServiceData($obj->service);
        }
    }

    public static function assertValidSpanContextData(SpanContextData $obj): void
    {
        self::assertValidExecutionSegmentContextData($obj);

        if ($obj->db !== null) {
            self::assertValidSpanContextDbData($obj->db);
        }

        if ($obj->http !== null) {
            self::assertValidSpanContextHttpData($obj->http);
        }

        if ($obj->destination !== null) {
            self::assertValidSpanContextDestinationData($obj->destination);
        }
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
        self::assertValidKeywordString($errorTransactionData->name);
        self::assertValidKeywordString($errorTransactionData->type);
    }

    /**
     * @param mixed $value
     *
     * @return int|string|null
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
        self::assertValidTimestamp($error->timestamp);
        self::assertValidErrorId($error->id);

        self::assertThat(is_null($error->traceId) === is_null($error->transactionId));
        self::assertThat(is_null($error->traceId) === is_null($error->parentId));
        self::assertThat(is_null($error->traceId) === is_null($error->transaction));

        if (!is_null($error->traceId)) {
            self::assertValidTraceId($error->traceId);
        }
        if (!is_null($error->transactionId)) {
            self::assertValidExecutionSegmentId($error->transactionId);
        }
        if (!is_null($error->parentId)) {
            self::assertValidExecutionSegmentId($error->parentId);
        }
        if (!is_null($error->transaction)) {
            self::assertValidErrorTransactionData($error->transaction);
        }

        if (!is_null($error->context)) {
            self::assertValidTransactionContextData($error->context);
        }

        self::assertValidNullableNonKeywordString($error->culprit);

        if (!is_null($error->exception)) {
            self::assertValidErrorExceptionData($error->exception);
        }
    }

    /**
     * @param mixed $samples
     *
     * @return array<string, array<string, float|int>>
     */
    public static function assertValidMetricSetDataSamples($samples): array
    {
        self::assertThat(is_array($samples));
        /** @var array<mixed, mixed> $samples */
        self::assertThat(!empty($samples));

        foreach ($samples as $key => $valueArr) {
            self::assertValidKeywordString($key);
            self::assertThat(is_array($valueArr));
            /** @var array<mixed, mixed> $valueArr */
            self::assertThat(count($valueArr) === 1);
            self::assertThat(array_key_exists('value', $valueArr));
            $value = $valueArr['value'];
            self::assertThat(is_int($value) || is_float($value));
            /** @var float|int $value */
        }
        /** @var array<string, array<string, float|int>> $samples */
        return $samples;
    }

    public static function assertValidMetricSetData(MetricSetData $metricSet): void
    {
        self::assertValidTimestamp($metricSet->timestamp);

        self::assertValidNullableKeywordString($metricSet->transactionName);
        self::assertValidNullableKeywordString($metricSet->transactionType);
        self::assertValidNullableKeywordString($metricSet->spanType);
        self::assertValidNullableKeywordString($metricSet->spanSubtype);

        self::assertThat(($metricSet->transactionName === null) === ($metricSet->transactionType === null));
        self::assertThat(BoolUtil::ifThen($metricSet->spanType !== null, $metricSet->transactionName !== null));
        self::assertThat(BoolUtil::ifThen($metricSet->spanSubtype !== null, $metricSet->spanType !== null));

        self::assertValidMetricSetDataSamples($metricSet->samples);
    }

    /**
     * @param mixed $pid
     *
     * @return int
     */
    public static function assertValidProcessId($pid): int
    {
        self::assertThat(is_int($pid));
        /** @var int $pid */
        self::assertThat($pid > 0);

        return $pid;
    }

    public static function assertValidProcessData(ProcessData $processData): void
    {
        self::assertValidProcessId($processData->pid);
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
        self::assertValidKeywordString($serviceData->name);
        self::assertValidNullableKeywordString($serviceData->nodeConfiguredName);
        self::assertValidNullableKeywordString($serviceData->version);
        self::assertValidNullableKeywordString($serviceData->environment);

        self::assertValidNameVersionData($serviceData->agent);
        assert($serviceData->agent !== null);
        self::assertThat($serviceData->agent->name === MetadataDiscoverer::AGENT_NAME);
        self::assertThat($serviceData->agent->version === ElasticApm::VERSION);
        self::assertValidNullableKeywordString($serviceData->agent->ephemeralId);

        self::assertValidNameVersionData($serviceData->framework);

        self::assertValidNameVersionData($serviceData->language);
        assert($serviceData->language !== null);
        self::assertThat($serviceData->language->name === MetadataDiscoverer::LANGUAGE_NAME);

        self::assertValidNameVersionData($serviceData->runtime);
        self::assertThat($serviceData->runtime !== null);
        assert($serviceData->runtime !== null);
        self::assertThat($serviceData->runtime->name === MetadataDiscoverer::LANGUAGE_NAME);
        self::assertThat($serviceData->runtime->version === $serviceData->language->version);
    }

    public static function assertValidSystemData(SystemData $systemData): void
    {
        self::assertValidNullableKeywordString($systemData->hostname);
        self::assertValidNullableKeywordString($systemData->configuredHostname);
        self::assertValidNullableKeywordString($systemData->detectedHostname);

        if ($systemData->configuredHostname !== null) {
            self::assertThat($systemData->detectedHostname === null);
            self::assertThat($systemData->hostname === $systemData->configuredHostname);
        } else {
            self::assertThat($systemData->hostname === $systemData->detectedHostname);
        }
    }

    public static function assertValidMetadata(Metadata $metadata): void
    {
        self::assertValidServiceData($metadata->service);
        self::assertValidProcessData($metadata->process);
    }
}
