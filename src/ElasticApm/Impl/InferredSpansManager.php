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
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LogStreamInterface;
use Elastic\Apm\Impl\Util\Assert;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class InferredSpansManager implements LoggableInterface
{
    use LoggableTrait;

    private const STATE_SHUTDOWN = 'shutdown';
    private const STATE_WAITING_FOR_NO_SPANS = 'waiting_for_no_spans';
    private const STATE_WAITING_FOR_NEW_TRANSACTION = 'waiting_for_no_spans';
    private const STATE_RUNNING = 'running';

    /** @var string */
    private $state = self::STATE_SHUTDOWN;

    /** @var Logger */
    private $logger;

    /** @var Tracer */
    private $tracer;

    /** @var bool */
    private $originalPcntlAsyncSignalsEnabled;

    /** @var ?InferredSpansBuilder */
    private $builder = null;

    /** @var null|Closure(Transaction): void */
    private $onNewCurrentTransactionHasBegunCallback = null;

    /** @var ?Transaction */
    private $currentTransaction = null;

    /** @var null|Closure(Transaction): void */
    private $onCurrentTransactionAboutToEndCallback = null;

    /** @var null|Closure(?Span): void */
    private $onCurrentSpanChangedCallback = null;

    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;
        $this->logger = $tracer->loggerFactory()
                               ->loggerForClass(LogCategory::INFERRED_SPANS, __NAMESPACE__, __CLASS__, __FILE__)
                               ->addContext('this', $this);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered');

        $currentTransaction = $tracer->getCurrentTransaction();
        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that($currentTransaction instanceof Transaction && $currentTransaction->isSampled())
        && $assertProxy->withContext(
            '$currentTransaction instanceof Transaction && $currentTransaction->isSampled()',
            ['currentTransaction' => $currentTransaction]
        );
        /** @var Transaction $currentTransaction */

        if (!$this->detectIfEnabled()) {
            return;
        }

        $this->tracer->onNewCurrentTransactionHasBegun->add(
            $this->onNewCurrentTransactionHasBegunCallback = function (Transaction $transaction): void {
                $this->onNewCurrentTransactionHasBegun($transaction);
            }
        );

        $this->state = self::STATE_WAITING_FOR_NEW_TRANSACTION;
        $this->onNewCurrentTransactionHasBegun($currentTransaction);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Inferred spans feature is enabled');
    }

    private function detectIfEnabled(): bool
    {
        if (!$this->tracer->getConfig()->profilingInferredSpansEnabled()) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Inferred spans feature is disabled because configuration option '
                . OptionNames::PROFILING_INFERRED_SPANS_ENABLED . ' value is false'
            );
            return false;
        }

        if (!extension_loaded('pcntl')) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Inferred spans feature is disabled because pcntl PHP extension is not loaded');
            return false;
        }

        return true;
    }

    private function enablePeriodicAlarmSignal(): bool
    {
        $installSignalHandlerRetVal = pcntl_signal(
            SIGALRM,
            function (): void {
                $this->handleAlarmSignal();
            }
        );
        if (!$installSignalHandlerRetVal) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Inferred spans feature is disabled because pcntl_signal returned false (meaning failure)'
            );
            return false;
        }

        $this->originalPcntlAsyncSignalsEnabled = pcntl_async_signals(true);
        $this->setAlarmTimer();

        return true;
    }

    private function disablePeriodicAlarmSignal(): void
    {
        // Disable SIGALRM
        pcntl_alarm(/* seconds */ 0);
        pcntl_async_signals($this->originalPcntlAsyncSignalsEnabled);

        $installSignalHandlerRetVal = pcntl_signal(SIGALRM, SIG_DFL);
        if (!$installSignalHandlerRetVal) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'While setting SIGALRM signal handler back to default pcntl_signal returned false (meaning failure)'
            );
        }
    }

    private function handleAlarmSignal(): void
    {
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered');

        if (!$this->isShutdown() && $this->builder !== null) {
            $stackTrace = InferredSpansBuilder::captureStackTrace(/* offset */ 1, $this->tracer->loggerFactory());
            $this->builder->addStackTrace($stackTrace);
            $this->setAlarmTimer();
        }

        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Exiting');
    }

    private function setAlarmTimer(): void
    {
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered');

        pcntl_alarm(/* seconds */ 1);

        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Exiting');
    }

    private function flushAndPause(): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered');

        if ($this->isShutdown()) {
            return;
        }

        $this->disablePeriodicAlarmSignal();
        if ($this->builder !== null) {
            $this->builder->close();
            $this->builder = null;
        }
    }

    private function onNewCurrentTransactionHasBegun(Transaction $transaction): void
    {
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered', ['transaction' => $transaction]);

        if ($this->isShutdown()) {
            return;
        }

        if ($this->currentTransaction !== null) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Unexpected: New transaction has begun'
                . ' while there is already a transaction in progress'
                . ' - shutting down...',
                ['old transaction' => $this->currentTransaction, 'new transaction' => $transaction]
            );
            $this->shutdown();
            return;
        }

        if ($transaction !== $this->tracer->getCurrentTransaction()) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                "Unexpected: New transaction has begun but it's not the current transaction"
                . ' - shutting down...',
                ['new transaction' => $transaction, 'current transaction' => $this->tracer->getCurrentTransaction()]
            );
            $this->shutdown();
            return;
        }

        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that($this->state === self::STATE_WAITING_FOR_NEW_TRANSACTION)
        && $assertProxy->withContext('$this->state === self::STATE_WAITING_FOR_NEW_TRANSACTION', ['this' => $this]);

        if (!$this->enablePeriodicAlarmSignal()) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Failed to enable periodic alarm signal - shutting down...');
            $this->shutdown();
            return;
        }

        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that($this->currentTransaction === null)
        && $assertProxy->withContext('$this->currentTransaction === null', ['this' => $this]);
        $this->currentTransaction = $transaction;

        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that($this->onCurrentTransactionAboutToEndCallback === null)
        && $assertProxy->withContext('$this->onTransactionAboutToEndCallback === null', ['this' => $this]);
        $this->currentTransaction->onAboutToEnd->add(
            $this->onCurrentTransactionAboutToEndCallback = function (Transaction $transaction): void {
                $this->onCurrentTransactionAboutToEnd($transaction);
            }
        );

        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that($this->onCurrentSpanChangedCallback === null)
        && $assertProxy->withContext('$this->onCurrentSpanChangedCallback === null', ['this' => $this]);
        $this->currentTransaction->onCurrentSpanChanged->add(
            $this->onCurrentSpanChangedCallback = function (?Span $span): void {
                $this->onCurrentSpanChanged($span);
            }
        );

        $this->builder = new InferredSpansBuilder($this->tracer);
        $this->state = self::STATE_RUNNING;
    }

    private function onCurrentTransactionAboutToEnd(Transaction $transaction): void
    {
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered', ['transaction' => $transaction]);

        if ($this->isShutdown()) {
            return;
        }

        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that($this->currentTransaction === $transaction)
        && $assertProxy->withContext(
            '$this->currentTransaction === $transaction',
            ['this' => $this, 'transaction' => $transaction]
        );

        $this->flushAndPause();

        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that($this->onCurrentTransactionAboutToEndCallback !== null)
        && $assertProxy->withContext('$this->onTransactionAboutToEndCallback !== null', ['this' => $this]);
        /* @phpstan-ignore-next-line */
        $this->currentTransaction->onAboutToEnd->remove($this->onCurrentTransactionAboutToEndCallback);
        $this->onCurrentTransactionAboutToEndCallback = null;

        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that($this->onCurrentSpanChangedCallback !== null)
        && $assertProxy->withContext('$this->onCurrentSpanChangedCallback !== null', ['this' => $this]);
        /* @phpstan-ignore-next-line */
        $this->currentTransaction->onCurrentSpanChanged->remove($this->onCurrentSpanChangedCallback);
        $this->onCurrentSpanChangedCallback = null;
        $this->currentTransaction = null;

        $this->state = self::STATE_WAITING_FOR_NEW_TRANSACTION;
    }

    private function onCurrentSpanChanged(?Span $span): void
    {
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered', ['span' => $span]);

        if ($this->isShutdown()) {
            return;
        }

        if ($span === null) {
            if ($this->state === self::STATE_RUNNING) {
                return;
            }
            if (!$this->enablePeriodicAlarmSignal()) {
                ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Failed to enabled periodic alarm signal - shutting down...');
                $this->shutdown();
                return;
            }

            $this->builder = new InferredSpansBuilder($this->tracer);
            $this->state = self::STATE_RUNNING;
            return;
        }

        $this->flushAndPause();
        $this->state = self::STATE_WAITING_FOR_NO_SPANS;
    }

    private function isShutdown(): bool
    {
        return $this->state === self::STATE_SHUTDOWN;
    }

    public function shutdown(): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered');

        if ($this->isShutdown()) {
            return;
        }

        $this->flushAndPause();

        if ($this->onNewCurrentTransactionHasBegunCallback !== null) {
            $this->tracer->onNewCurrentTransactionHasBegun->remove($this->onNewCurrentTransactionHasBegunCallback);
            $this->onNewCurrentTransactionHasBegunCallback = null;
        }

        if ($this->currentTransaction !== null) {
            if ($this->onCurrentTransactionAboutToEndCallback !== null) {
                $this->currentTransaction->onAboutToEnd->remove($this->onCurrentTransactionAboutToEndCallback);
                $this->onCurrentTransactionAboutToEndCallback = null;
            }
            $this->currentTransaction = null;
        }

        $this->state = self::STATE_SHUTDOWN;
    }

    /**
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return array_merge(self::defaultPropertiesExcludedFromLog(), ['tracer', 'currentTransaction']);
    }

    /** @inheritDoc */
    public function toLog(LogStreamInterface $stream): void
    {
        $currentTransactionId = $this->currentTransaction === null ? null : $this->currentTransaction->getId();
        $this->toLogLoggableTraitImpl(
            $stream,
            /* customPropValues */
            ['currentTransactionId' => $currentTransactionId]
        );
    }
}
