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

use Closure;
use Elastic\Apm\CustomErrorData;
use Elastic\Apm\ElasticApm;
use Elastic\Apm\ExecutionSegmentInterface;
use Elastic\Apm\Impl\AutoInstrument\PhpErrorData;
use Elastic\Apm\Impl\BackendComm\EventSender;
use Elastic\Apm\Impl\BreakdownMetrics\PerTransaction as BreakdownMetricsPerTransaction;
use Elastic\Apm\Impl\Config\DevInternalSubOptionNames;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Config\Snapshot as ConfigSnapshot;
use Elastic\Apm\Impl\Log\Backend as LogBackend;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Log\LogStreamInterface;
use Elastic\Apm\Impl\Util\ElasticApmExtensionUtil;
use Elastic\Apm\Impl\Util\ObserverSet;
use Elastic\Apm\Impl\Util\PhpErrorUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use Elastic\Apm\TransactionBuilderInterface;
use Elastic\Apm\TransactionInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Tracer implements TracerInterface, LoggableInterface
{
    /** @var TracerDependencies */
    private $providedDependencies;

    /** @var ClockInterface */
    private $clock;

    /** @var EventSinkInterface */
    private $eventSink;

    /** @var ConfigSnapshot */
    private $config;

    /** @var LogBackend */
    private $logBackend;

    /** @var LoggerFactory */
    private $loggerFactory;

    /** @var Logger */
    private $logger;

    /** @var ?Transaction */
    private $currentTransaction = null;

    /** @var bool */
    private $isRecording = true;

    /** @var Metadata */
    private $currentMetadata;

    /** @var HttpDistributedTracing */
    private $httpDistributedTracing;

    /** @var ObserverSet<Transaction> */
    public $onNewCurrentTransactionHasBegun;

    public function __construct(TracerDependencies $providedDependencies, ConfigSnapshot $config)
    {
        $this->providedDependencies = $providedDependencies;
        $this->config = $config;

        $this->logBackend = new LogBackend($this->config->effectiveLogLevel(), $providedDependencies->logSink);
        $this->loggerFactory = new LoggerFactory($this->logBackend);
        $this->logger = $this->loggerFactory
            ->loggerForClass(LogCategory::PUBLIC_API, __NAMESPACE__, __CLASS__, __FILE__)->addContext('this', $this);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Constructing Tracer...',
            [
                'Version of agent PHP part' => ElasticApm::VERSION,
                'PHP_VERSION'               => PHP_VERSION,
                'providedDependencies'      => $providedDependencies,
                'effectiveLogLevel'         => LogLevel::intToName($this->config->effectiveLogLevel()),
            ]
        );

        $this->clock = $providedDependencies->clock ?? new Clock($this->loggerFactory);

        $this->eventSink = $providedDependencies->eventSink ??
                           (ElasticApmExtensionUtil::isLoaded()
                               ? new EventSender($this->config, $this->loggerFactory)
                               : NoopEventSink::singletonInstance());

        $this->currentMetadata = MetadataDiscoverer::discoverMetadata($this->config, $this->loggerFactory);

        $this->httpDistributedTracing = new HttpDistributedTracing($this->loggerFactory);

        $this->onNewCurrentTransactionHasBegun = new ObserverSet();

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Constructed Tracer successfully');
    }

    public function getConfig(): ConfigSnapshot
    {
        return $this->config;
    }

    private function newTransactionBuilder(
        string $name,
        string $type,
        ?float $timestamp = null,
        ?string $serializedDistTracingData = null
    ): TransactionBuilder {
        $builder = new TransactionBuilder($this, $name, $type);
        $builder->timestamp = $timestamp;
        $builder->serializedDistTracingData = $serializedDistTracingData;
        return $builder;
    }

    /** @inheritDoc */
    public function beginCurrentTransaction(
        string $name,
        string $type,
        ?float $timestamp = null,
        ?string $serializedDistTracingData = null
    ): TransactionInterface {
        $builder = $this->newTransactionBuilder($name, $type, $timestamp, $serializedDistTracingData);
        $builder->asCurrent();
        return $this->beginTransactionWithBuilder($builder);
    }

    /** @inheritDoc */
    public function captureCurrentTransaction(
        string $name,
        string $type,
        Closure $callback,
        ?float $timestamp = null,
        ?string $serializedDistTracingData = null
    ) {
        $builder = $this->newTransactionBuilder($name, $type, $timestamp, $serializedDistTracingData);
        $builder->asCurrent();
        return $this->captureTransactionWithBuilder($builder, $callback);
    }

    /** @inheritDoc */
    public function getCurrentTransaction(): TransactionInterface
    {
        return $this->currentTransaction ?? NoopTransaction::singletonInstance();
    }

    /** @inheritDoc */
    public function getCurrentExecutionSegment(): ExecutionSegmentInterface
    {
        if ($this->currentTransaction === null) {
            return NoopTransaction::singletonInstance();
        }

        return $this->currentTransaction->getCurrentExecutionSegment();
    }

    public function resetCurrentTransaction(): void
    {
        $this->currentTransaction = null;
    }

    /** @inheritDoc */
    public function beginTransaction(
        string $name,
        string $type,
        ?float $timestamp = null,
        ?string $serializedDistTracingData = null
    ): TransactionInterface {
        $builder = $this->newTransactionBuilder($name, $type, $timestamp, $serializedDistTracingData);
        return $this->beginTransactionWithBuilder($builder);
    }

    /** @inheritDoc */
    public function captureTransaction(
        string $name,
        string $type,
        Closure $callback,
        ?float $timestamp = null,
        ?string $serializedDistTracingData = null
    ) {
        $builder = $this->newTransactionBuilder($name, $type, $timestamp, $serializedDistTracingData);
        return $this->captureTransactionWithBuilder($builder, $callback);
    }

    /** @inheritDoc */
    public function newTransaction(string $name, string $type): TransactionBuilderInterface
    {
        return new TransactionBuilder($this, $name, $type);
    }

    public function beginTransactionWithBuilder(TransactionBuilder $builder): TransactionInterface
    {
        if (!$this->isRecording) {
            return NoopTransaction::singletonInstance();
        }

        $newTransaction = new Transaction($builder);

        if ($builder->asCurrent) {
            if ($this->currentTransaction !== null) {
                ($loggerProxy = $this->logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Received request to begin a new current transaction'
                    . ' even though there is a current transaction that is still not ended',
                    ['this' => $this]
                );
            }

            $this->currentTransaction = $newTransaction;
            $this->onNewCurrentTransactionHasBegun->callCallbacks($this->currentTransaction);
        }

        return $newTransaction;
    }

    /**
     * @param TransactionBuilder                       $builder
     * @param Closure                                  $callback
     *
     * @return mixed The return value of $callback
     *
     * @template T
     * @phpstan-param Closure(TransactionInterface): T $callback Callback to execute as the new transaction
     * @phpstan-return T The return value of $callback
     */
    public function captureTransactionWithBuilder(TransactionBuilder $builder, Closure $callback)
    {
        $newTransaction = $this->beginTransactionWithBuilder($builder);
        try {
            return $callback($newTransaction);
        } catch (Throwable $throwable) {
            $newTransaction->createErrorFromThrowable($throwable);
            throw $throwable;
        } finally {
            $newTransaction->end();
        }
    }

    public function onPhpError(PhpErrorData $phpErrorData, ?Throwable $relatedThrowable): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Entered',
            [
                'phpErrorData'     => $phpErrorData,
                'relatedThrowable' => $relatedThrowable,
            ]
        );

        if ((error_reporting() & $phpErrorData->type) === 0) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Not creating error event because error_reporting() does not include its type',
                ['type' => $phpErrorData->type, 'error_reporting()' => error_reporting()]
            );
            return;
        }
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Creating error event because error_reporting() includes its type...',
            ['type' => $phpErrorData->type, 'error_reporting()' => error_reporting()]
        );

        $customErrorData = new CustomErrorData();
        $customErrorData->code = $phpErrorData->type;
        if (
            ($phpErrorData->message === null)
            || ($phpErrorData->fileName === null)
            || TextUtil::contains($phpErrorData->message, $phpErrorData->fileName)
        ) {
            $customErrorData->message = $phpErrorData->message;
        } else {
            $messageSuffix = ' in ' . $phpErrorData->fileName;
            if ($phpErrorData->lineNumber !== null) {
                $messageSuffix .= ':' . $phpErrorData->lineNumber;
            }
            $customErrorData->message = $phpErrorData->message . $messageSuffix;
        }

        if ($phpErrorData->type !== null) {
            $customErrorData->type = PhpErrorUtil::getTypeName($phpErrorData->type);
        }

        $this->createError($customErrorData, $phpErrorData, $relatedThrowable);
    }

    private function createError(
        ?CustomErrorData $customErrorData,
        ?PhpErrorData $phpErrorData,
        ?Throwable $throwable
    ): ?string {
        return $this->dispatchCreateError(
            ErrorExceptionData::build(
                $this,
                $customErrorData,
                $phpErrorData,
                $throwable
            )
        );
    }

    /** @inheritDoc */
    public function createErrorFromThrowable(Throwable $throwable): ?string
    {
        return $this->createError(/* customErrorData: */ null, /* phpErrorData: */ null, $throwable);
    }

    /** @inheritDoc */
    public function createCustomError(CustomErrorData $customErrorData): ?string
    {
        return $this->createError($customErrorData, /* phpErrorData: */ null, /* throwable: */ null);
    }

    private function dispatchCreateError(ErrorExceptionData $errorExceptionData): ?string
    {
        if ($this->currentTransaction === null) {
            return $this->doCreateError($errorExceptionData, /* transaction */ null, /* span */ null);
        }

        return $this->currentTransaction->dispatchCreateError($errorExceptionData);
    }

    public function doCreateError(
        ErrorExceptionData $errorExceptionData,
        ?Transaction $transaction,
        ?Span $span
    ): ?string {
        if (!$this->isRecording) {
            return null;
        }

        if ($transaction !== null && ($transaction->numberOfErrorsSent >= $this->config->transactionMaxSpans())) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Starting to drop errors because of ' . OptionNames::TRANSACTION_MAX_SPANS . ' config',
                [
                    '$transaction->numberOfErrorsSent'             => $transaction->numberOfErrorsSent,
                    OptionNames::TRANSACTION_MAX_SPANS . ' config' => $this->config->transactionMaxSpans(),
                ]
            );
            return null;
        }

        $newError = Error::build(/* tracer: */ $this, $errorExceptionData, $transaction, $span);
        $this->sendErrorToApmServer($newError);

        if ($transaction !== null) {
            ++$transaction->numberOfErrorsSent;
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Error event has been sent',
                ['$transaction->numberOfErrorsSent' => $transaction->numberOfErrorsSent]
            );
        }

        return $newError->id;
    }

    public function getClock(): ClockInterface
    {
        return $this->clock;
    }

    public function isNoop(): bool
    {
        return false;
    }

    public function limitString(string $val, bool $enforceKeywordString): string
    {
        return $enforceKeywordString ? self::limitKeywordString($val) : $this->limitNonKeywordString($val);
    }

    public static function limitKeywordString(string $keywordString): string
    {
        return TextUtil::ensureMaxLength($keywordString, Constants::KEYWORD_STRING_MAX_LENGTH);
    }

    public static function limitNullableKeywordString(?string $keywordString): ?string
    {
        if ($keywordString === null) {
            return null;
        }

        return self::limitKeywordString($keywordString);
    }

    public function limitNonKeywordString(string $nonKeywordString): string
    {
        return TextUtil::ensureMaxLength($nonKeywordString, $this->config->nonKeywordStringMaxLength());
    }

    public function limitNullableNonKeywordString(?string $nonKeywordString): ?string
    {
        if ($nonKeywordString === null) {
            return null;
        }

        return $this->limitNonKeywordString($nonKeywordString);
    }

    public function loggerFactory(): LoggerFactory
    {
        return $this->loggerFactory;
    }

    public function httpDistributedTracing(): HttpDistributedTracing
    {
        return $this->httpDistributedTracing;
    }

    /** @inheritDoc */
    public function pauseRecording(): void
    {
        $this->isRecording = false;
    }

    /** @inheritDoc */
    public function resumeRecording(): void
    {
        $this->isRecording = true;
    }

    /** @inheritDoc */
    public function isRecording(): bool
    {
        return $this->isRecording;
    }

    /**
     * @param SpanToSendInterface[]           $spans
     * @param Error[]                         $errors
     * @param ?BreakdownMetricsPerTransaction $breakdownMetricsPerTransaction
     * @param ?Transaction                    $transaction
     */
    private function sendEventsToApmServer(
        array $spans,
        array $errors,
        ?BreakdownMetricsPerTransaction $breakdownMetricsPerTransaction,
        ?Transaction $transaction
    ): void {
        if ($this->config->devInternal()->dropEventAfterEnd()) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Dropping span because '
                . OptionNames::DEV_INTERNAL . ' sub-option ' . DevInternalSubOptionNames::DROP_EVENT_AFTER_END
                . ' is set'
            );
            return;
        }

        $this->eventSink->consume(
            $this->currentMetadata,
            $spans,
            $errors,
            $breakdownMetricsPerTransaction,
            $transaction
        );
    }

    public function sendSpanToApmServer(SpanToSendInterface $span): void
    {
        self::sendEventsToApmServer(
            [$span] /* <- spans */,
            [] /* <- errors */,
            null /* <- breakdownMetricsPerTransaction */,
            null /* <- transaction */
        );
    }

    public function sendErrorToApmServer(Error $error): void
    {
        self::sendEventsToApmServer(
            [] /* <- spans */,
            [$error],
            null /* <- breakdownMetricsPerTransaction */,
            null /* <- transaction */
        );
    }

    public function sendTransactionToApmServer(
        ?BreakdownMetricsPerTransaction $breakdownMetricsPerTransaction,
        Transaction $transaction
    ): void {
        self::sendEventsToApmServer(
            [] /* <- spans */,
            [] /* <- errors */,
            $breakdownMetricsPerTransaction,
            $transaction
        );
    }

    /** @inheritDoc */
    public function setAgentEphemeralId(?string $ephemeralId): void
    {
        assert(isset($this->currentMetadata->service->agent));
        $this->currentMetadata->service->agent->ephemeralId = $this->limitNullableKeywordString($ephemeralId);
    }

    /** @inheritDoc */
    public function getSerializedCurrentDistributedTracingData(): string
    {
        /** @noinspection PhpDeprecationInspection */
        $distTracingData = $this->currentTransaction !== null
            ? $this->currentTransaction->getDistributedTracingData()
            : null;

        /** @noinspection PhpDeprecationInspection */
        return $distTracingData !== null
            ? $distTracingData->serializeToString()
            : NoopDistributedTracingData::serializedToString();
    }

    /** @inheritDoc */
    public function injectDistributedTracingHeaders(Closure $headerInjector): void
    {
        if ($this->currentTransaction === null) {
            return;
        }

        /** @noinspection PhpDeprecationInspection */
        $distTracingData = $this->currentTransaction->getDistributedTracingData();
        if ($distTracingData !== null) {
            $distTracingData->injectHeaders($headerInjector);
        }
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $result = [
            'isRecording'          => $this->isRecording,
            'providedDependencies' => $this->providedDependencies,
            'config'               => $this->config,
        ];

        if ($this->currentTransaction !== null) {
            $result['currentTransactionId'] = $this->currentTransaction->getId();
        }

        $stream->toLogAs($result);
    }
}
