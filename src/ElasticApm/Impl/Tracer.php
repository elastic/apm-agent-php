<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\Impl\BackendComm\EventSender;
use Elastic\Apm\Impl\Config\AllOptionsMetadata;
use Elastic\Apm\Impl\Config\CompositeRawSnapshotSource;
use Elastic\Apm\Impl\Config\EnvVarsRawSnapshotSource;
use Elastic\Apm\Impl\Config\IniRawSnapshotSource;
use Elastic\Apm\Impl\Config\Parser as ConfigParser;
use Elastic\Apm\Impl\Config\Snapshot as ConfigSnapshot;
use Elastic\Apm\Impl\Log\Backend as LogBackend;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Log\LogStreamInterface;
use Elastic\Apm\Impl\Util\ElasticApmExtensionUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use Elastic\Apm\TransactionInterface;

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

    /** @var TransactionInterface|null */
    private $currentTransaction = null;

    /** @var bool */
    private $isRecording = true;

    public function __construct(TracerDependencies $providedDependencies)
    {
        $this->providedDependencies = $providedDependencies;

        $this->config = $this->buildConfig();

        $this->clock = $providedDependencies->clock ?? Clock::singletonInstance();

        $this->logBackend = new LogBackend(LogLevel::TRACE, $providedDependencies->logSink);
        $this->loggerFactory = new LoggerFactory($this->logBackend);
        $this->logger = $this->loggerFactory
            ->loggerForClass(LogCategory::PUBLIC_API, __NAMESPACE__, __CLASS__, __FILE__);

        $this->eventSink = $providedDependencies->eventSink ??
                           (ElasticApmExtensionUtil::isLoaded()
                               ? new EventSender($this->config, $this->loggerFactory)
                               : NoopEventSink::singletonInstance());

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Tracer created',
            [
                'providedDependencies' => $providedDependencies,
                'this'                 => $this,
            ]
        );

        $this->eventSink->setMetadata(MetadataDiscoverer::discoverMetadata($this->config));
    }

    private function buildConfig(): ConfigSnapshot
    {
        $rawSnapshotSource
            = $this->providedDependencies->configRawSnapshotSource
              ?? new CompositeRawSnapshotSource(
                  [
                      new IniRawSnapshotSource(IniRawSnapshotSource::DEFAULT_PREFIX),
                      new EnvVarsRawSnapshotSource(EnvVarsRawSnapshotSource::DEFAULT_NAME_PREFIX),
                  ]
              );

        $parsingLoggerFactory
            = new LoggerFactory(new LogBackend(LogLevel::TRACE, $this->providedDependencies->logSink));
        $parser = new ConfigParser($parsingLoggerFactory);
        $allOptsMeta = AllOptionsMetadata::build();
        return new ConfigSnapshot($parser->parse($allOptsMeta, $rawSnapshotSource->currentSnapshot($allOptsMeta)));
    }

    public function getConfig(): ConfigSnapshot
    {
        return $this->config;
    }

    /** @inheritDoc */
    public function beginTransaction(string $name, string $type, ?float $timestamp = null): TransactionInterface
    {
        if (!$this->isRecording) {
            return NoopTransaction::singletonInstance();
        }
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
        return $this->currentTransaction ?? NoopTransaction::singletonInstance();
    }

    public function resetCurrentTransaction(): void
    {
        $this->currentTransaction = null;
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

    public function loggerFactory(): LoggerFactory
    {
        return $this->loggerFactory;
    }

    public function pauseRecording(): void
    {
        $this->isRecording = false;
    }

    public function resumeRecording(): void
    {
        $this->isRecording = true;
    }

    public function isRecording(): bool
    {
        return $this->isRecording;
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs(
            [
                'isRecording'          => $this->isRecording,
                'providedDependencies' => $this->providedDependencies,
                'config'               => $this->config,
            ]
        );
    }
}
