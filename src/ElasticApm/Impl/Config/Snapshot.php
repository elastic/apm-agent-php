<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Snapshot implements LoggableInterface
{
    use SnapshotTrait;
    use LoggableTrait;

    /** @var bool */
    private $enabled;

    /** @var string|null */
    private $environment;

    /** @var int|null */
    private $logLevel;

    /** @var int|null */
    private $logLevelStderr;

    /** @var int|null */
    private $logLevelSyslog;

    /** @var float - In milliseconds */
    private $serverTimeout;

    /** @var string|null */
    private $serviceName;

    /** @var string|null */
    private $serviceVersion;

    /** @var int */
    private $transactionMaxSpans;

    /** @var float */
    private $transactionSampleRate;

    /** @var bool */
    private $verifyServerCert;

    /**
     * Snapshot constructor.
     *
     * @param array<string, mixed> $optNameToParsedValue
     */
    public function __construct(array $optNameToParsedValue)
    {
        $this->setPropertiesToValuesFrom($optNameToParsedValue);
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function environment(): ?string
    {
        return $this->environment;
    }

    public function effectiveLogLevel(): int
    {
        $effectiveLogLevelStderr = ($this->logLevelStderr ?? $this->logLevel) ?? LogLevel::INFO;
        $effectiveLogLevelSyslog = ($this->logLevelSyslog ?? $this->logLevel) ?? LogLevel::CRITICAL;
        return max($effectiveLogLevelStderr, $effectiveLogLevelSyslog, $this->logLevel ?? LogLevel::OFF);
    }

    public function serverTimeout(): float
    {
        return $this->serverTimeout;
    }

    public function serviceName(): ?string
    {
        return $this->serviceName;
    }

    public function serviceVersion(): ?string
    {
        return $this->serviceVersion;
    }

    public function transactionMaxSpans(): int
    {
        return $this->transactionMaxSpans;
    }

    public function transactionSampleRate(): float
    {
        return $this->transactionSampleRate;
    }
}
