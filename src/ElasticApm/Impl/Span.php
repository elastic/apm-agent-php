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
use Elastic\Apm\DistributedTracingData;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\SpanContextInterface;
use Elastic\Apm\SpanInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Span extends ExecutionSegment implements SpanInterface
{
    /** @var SpanData */
    private $data;

    /** @var Logger */
    private $logger;

    /** @var Transaction */
    private $containingTransaction;

    /** @var Span|null */
    private $parentSpan;

    /** @var bool */
    private $isDropped;

    /** @var SpanContext|null */
    private $context = null;

    public function __construct(
        Tracer $tracer,
        Transaction $containingTransaction,
        ?Span $parentSpan,
        string $name,
        string $type,
        ?string $subtype,
        ?string $action,
        ?float $timestamp,
        bool $isDropped
    ) {
        $this->data = new SpanData();

        parent::__construct(
            $this->data,
            $tracer,
            $containingTransaction->getTraceId(),
            $name,
            $type,
            $timestamp
        );

        $this->setSubtype($subtype);
        $this->setAction($action);

        $this->containingTransaction = $containingTransaction;
        $this->data->transactionId = $containingTransaction->getId();

        $this->parentSpan = $parentSpan;
        $this->data->parentId = is_null($parentSpan) ? $containingTransaction->getId() : $parentSpan->getId();

        $this->logger = $this->tracer->loggerFactory()
                                     ->loggerForClass(LogCategory::PUBLIC_API, __NAMESPACE__, __CLASS__, __FILE__)
                                     ->addContext('this', $this);

        $this->isDropped = $isDropped;

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Span created');
    }

    /**
     * @return array<string>
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return array_merge(
            parent::propertiesExcludedFromLog(),
            ['containingTransaction', 'parentSpan', 'logger', 'stacktrace', 'context']
        );
    }

    /** @inheritDoc */
    public function isSampled(): bool
    {
        return $this->containingTransaction->isSampled();
    }

    public function parentSpan(): ?Span
    {
        return $this->parentSpan;
    }

    private function shouldBeSentToApmServer(): bool
    {
        return $this->containingTransaction->isSampled() && (!$this->isDropped);
    }

    /** @inheritDoc */
    public function getParentId(): string
    {
        return $this->data->parentId;
    }

    /** @inheritDoc */
    public function getTransactionId(): string
    {
        return $this->data->transactionId;
    }

    /** @inheritDoc */
    public function context(): SpanContextInterface
    {
        if (!$this->isSampled()) {
            return NoopSpanContext::singletonInstance();
        }

        if (is_null($this->context)) {
            $this->data->context = new SpanContextData();
            $this->context = new SpanContext($this, $this->data->context);
        }

        return $this->context;
    }

    /** @inheritDoc */
    public function setAction(?string $action): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->action = $this->tracer->limitNullableKeywordString($action);
    }

    /** @inheritDoc */
    public function setSubtype(?string $subtype): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->subtype = $this->tracer->limitNullableKeywordString($subtype);
    }

    /** @inheritDoc */
    public function getDistributedTracingData(): ?DistributedTracingData
    {
        $spanAsParent = $this->shouldBeSentToApmServer() ? $this : null;
        return $this->containingTransaction->doGetDistributedTracingData($spanAsParent);
    }

    /** @inheritDoc */
    public function beginChildSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ): SpanInterface {
        return
            $this->containingTransaction->beginSpan(
                $this /* <- parentSpan */,
                $name,
                $type,
                $subtype,
                $action,
                $timestamp
            )
            ?? NoopSpan::singletonInstance();
    }

    /** @inheritDoc */
    public function captureChildSpan(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ) {
        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->captureChildSpanImpl(
            $name,
            $type,
            $callback,
            $subtype,
            $action,
            $timestamp,
            1 /* numberOfStackFramesToSkip */
        );
    }

    /** @inheritDoc */
    public function dispatchCreateError(?ErrorExceptionData $errorExceptionData): ?string
    {
        $spanForError = $this->shouldBeSentToApmServer() ? $this : null;
        return $this->tracer->doCreateError($errorExceptionData, $this->containingTransaction, $spanForError);
    }

    /** @inheritDoc */
    public function endSpanEx(int $numberOfStackFramesToSkip, ?float $duration = null): void
    {
        if (!$this->endExecutionSegment($duration)) {
            return;
        }

        // This method is part of public API so it should be kept in the stack trace
        // if $numberOfStackFramesToSkip is 0
        $this->data->stacktrace = StacktraceUtil::captureCurrent(
            $numberOfStackFramesToSkip,
            true /* <- hideElasticApmImpl */
        );

        if ((!is_null($this->data->context)) && $this->data->context->isEmpty()) {
            $this->data->context = null;
        }

        if ($this->shouldBeSentToApmServer()) {
            $this->containingTransaction->queueSpanDataToSend($this->data);
        }

        if ($this->containingTransaction->getCurrentSpan() === $this) {
            $this->containingTransaction->setCurrentSpan($this->parentSpan);
        }
    }

    /** @inheritDoc */
    public function end(?float $duration = null): void
    {
        // Since endSpanEx was not called directly it should not be kept in the stack trace
        $this->endSpanEx(/* numberOfStackFramesToSkip: */ 1, $duration);
    }
}
