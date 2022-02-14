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
use Elastic\Apm\Impl\BackendComm\EventSender;
use Elastic\Apm\Impl\BreakdownMetrics\PerTransaction as BreakdownMetricsPerTransaction;
use Elastic\Apm\Impl\Config\Snapshot as ConfigSnapshot;
use Elastic\Apm\Impl\Log\Backend as LogBackend;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Log\LogStreamInterface;
use Elastic\Apm\Impl\Util\ElasticApmExtensionUtil;
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

    /** @var Transaction|null */
    private $currentTransaction = null;

    /** @var bool */
    private $isRecording = true;

    /** @var Metadata */
    private $currentMetadata;

    /** @var HttpDistributedTracing */
    private $httpDistributedTracing;

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

        $this->clock = $providedDependencies->clock ?? Clock::singletonInstance();

        $this->eventSink = $providedDependencies->eventSink ??
                           (ElasticApmExtensionUtil::isLoaded()
                               ? new EventSender($this->config, $this->loggerFactory)
                               : NoopEventSink::singletonInstance());

        $this->currentMetadata = MetadataDiscoverer::discoverMetadata($this->config, $this->loggerFactory);

        $this->httpDistributedTracing = new HttpDistributedTracing($this->loggerFactory);

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

    public function onPhpError(
        int $type,
        string $fileName,
        int $lineNumber,
        string $message,
        ?Throwable $relatedThrowable
    ): void {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Entered',
            [
                'type' => $type,
                'fileName' => $fileName,
                'lineNumber' => $lineNumber,
                'message' => $message,
                'relatedThrowable' => $relatedThrowable,
            ]
        );

        $customErrorData = new CustomErrorData();
        $customErrorData->code = $type;
        $customErrorData->message = TextUtil::contains($message, $fileName)
            ? $message
            : ($message . ' in ' . $fileName . ':' . $lineNumber);
        $customErrorData->type = PhpErrorUtil::getTypeName($type);

        $this->createError($customErrorData, $relatedThrowable);
    }

    private function createError(?CustomErrorData $customErrorData, ?Throwable $throwable): ?string
    {
        return $this->dispatchCreateError(
            ErrorExceptionData::build(
                $this,
                $customErrorData,
                $throwable
            )
        );
    }

    /** @inheritDoc */
    public function createErrorFromThrowable(Throwable $throwable): ?string
    {
        return $this->createError(/* customErrorData: */ null, $throwable);
    }

    /** @inheritDoc */
    public function createCustomError(CustomErrorData $customErrorData): ?string
    {
        return $this->createError($customErrorData, /* throwable: */ null);
    }

    private function dispatchCreateError(ErrorExceptionData $errorExceptionData): ?string
    {
        if (is_null($this->currentTransaction)) {
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

        $isGoingToBeSentWithTransaction = $transaction !== null && !($transaction->hasEnded());

        // PHPStan cannot deduce that $transaction is not null
        // if $isGoingToBeSentWithTransaction is true
        // @phpstan-ignore-next-line
        if ($isGoingToBeSentWithTransaction && !$transaction->reserveSpaceInErrorToSendQueue()) {
            return null;
        }

        $newError = ErrorData::build(/* tracer: */ $this, $errorExceptionData, $transaction, $span);

        if ($isGoingToBeSentWithTransaction) {
            // PHPStan cannot deduce that $transaction is not null
            // if $isGoingToBeSentWithTransaction is true
            // @phpstan-ignore-next-line
            $transaction->queueErrorDataToSend($newError);
        } else {
            $this->sendEventsToApmServer(
                [] /* <- spansData */,
                [$newError],
                null /* <- breakdownMetricsPerTransaction */,
                null /* <- transactionData */
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

    public static function limitKeywordString(string $keywordString): string
    {
        return TextUtil::ensureMaxLength($keywordString, Constants::KEYWORD_STRING_MAX_LENGTH);
    }

    public static function limitNullableKeywordString(?string $keywordString): ?string
    {
        if (is_null($keywordString)) {
            return null;
        }

        return self::limitKeywordString($keywordString);
    }

    public function limitNonKeywordString(string $nonKeywordString): string
    {
        return TextUtil::ensureMaxLength($nonKeywordString, Constants::NON_KEYWORD_STRING_MAX_LENGTH);
    }

    public function limitNullableNonKeywordString(?string $nonKeywordString): ?string
    {
        if (is_null($nonKeywordString)) {
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
     * @param SpanData[]                      $spansData
     * @param ErrorData[]                     $errorsData
     * @param ?BreakdownMetricsPerTransaction $breakdownMetricsPerTransaction
     * @param ?TransactionData                $transactionData
     */
    public function sendEventsToApmServer(
        array $spansData,
        array $errorsData,
        ?BreakdownMetricsPerTransaction $breakdownMetricsPerTransaction,
        ?TransactionData $transactionData
    ): void {
        $this->eventSink->consume(
            $this->currentMetadata,
            $spansData,
            $errorsData,
            $breakdownMetricsPerTransaction,
            $transactionData
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

        if (!is_null($this->currentTransaction)) {
            $result['currentTransactionId'] = $this->currentTransaction->getId();
        }

        $stream->toLogAs($result);
    }
}
