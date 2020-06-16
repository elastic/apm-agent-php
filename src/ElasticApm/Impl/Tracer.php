<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\ServerComm\EventSender;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\Impl\Util\TextUtil;
use Elastic\Apm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Tracer implements TracerInterface
{
    /** @var TracerDependencies */
    private $providedDependencies;

    /** @var ClockInterface */
    private $clock;

    /** @var EventSinkInterface */
    private $eventSink;

    /** @var Log\Backend */
    private $logBackend;

    /** @var LoggerFactory */
    private $loggerFactory;

    /** @var Logger */
    private $logger;

    /** @var TransactionInterface|null */
    private $currentTransaction;

    public function __construct(TracerDependencies $providedDependencies)
    {
        $this->providedDependencies = $providedDependencies;

        $this->clock = $providedDependencies->clock ?? Clock::instance();
        $this->eventSink = $providedDependencies->eventSink ?? new EventSender();

        $this->logBackend = new Log\Backend($providedDependencies->logSink);
        $this->loggerFactory = new LoggerFactory($this->logBackend);
        $this->logger = $this->loggerFactory
            ->loggerForClass(LogCategory::PUBLIC_API, __NAMESPACE__, __CLASS__, __FILE__);

        $this->currentTransaction = null;

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Tracer created', ['providedDependencies' => $providedDependencies]);

        $this->eventSink->setMetadata(MetadataDiscoverer::discoverMetadata());
    }

    /** @inheritDoc */
    public function beginTransaction(string $name, string $type, ?float $timestamp = null): TransactionInterface
    {
        return new Transaction($this, $name, $type, $timestamp);
    }

    /** @inheritDoc */
    public function beginCurrentTransaction(string $name, string $type, ?float $timestamp = null): TransactionInterface
    {
        $this->currentTransaction = $this->beginTransaction($name, $type, $timestamp);
        return $this->currentTransaction;
    }

    /** @inheritDoc */
    public function captureTransaction(string $name, string $type, Closure $callback, ?float $timestamp = null)
    {
        $transaction = $this->beginTransaction($name, $type, $timestamp);
        try {
            return $callback($transaction);
        } finally {
            $transaction->end();
        }
    }

    /** @inheritDoc */
    public function captureCurrentTransaction(string $name, string $type, Closure $callback, ?float $timestamp = null)
    {
        $transaction = $this->beginCurrentTransaction($name, $type, $timestamp);
        try {
            return $callback($transaction);
        } finally {
            $transaction->end();
        }
    }

    public function getClock(): ClockInterface
    {
        return $this->clock;
    }

    public function getEventSink(): EventSinkInterface
    {
        return $this->eventSink;
    }

    public function isNoop(): bool
    {
        return false;
    }

    public function getCurrentTransaction(): TransactionInterface
    {
        return $this->currentTransaction ?? NoopTransaction::instance();
    }

    public function resetCurrentTransaction(): void
    {
        $this->currentTransaction = null;
    }

    public function limitKeywordString(string $keywordString): string
    {
        return TextUtil::ensureMaxLength($keywordString, Constants::KEYWORD_STRING_MAX_LENGTH);
    }

    public function limitNullableKeywordString(?string $keywordString): ?string
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

    public function loggerFactory(): LoggerFactory
    {
        return $this->loggerFactory;
    }

    public function __toString(): string
    {
        $builder = new ObjectToStringBuilder();
        $builder->add('providedDependencies', $this->providedDependencies);
        return $builder->build();
    }
}
