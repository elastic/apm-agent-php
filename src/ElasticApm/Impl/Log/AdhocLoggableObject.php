<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class AdhocLoggableObject implements LoggableInterface
{
    /** @var array<string, mixed> */
    private $propertyNameToData = [];

    /**
     * @param array<string, mixed> $nameToValue
     * @param int                  $logPriority
     */
    public function __construct(
        array $nameToValue,
        int $logPriority = PropertyLogPriority::NORMAL
    ) {
        $this->addProperties($nameToValue, $logPriority);
    }

    /**
     * @param array<string, mixed> $nameToValue
     * @param int                  $logPriority
     *
     * @return self
     */
    public function addProperties(
        array $nameToValue,
        /** @noinspection PhpUnusedParameterInspection */ int $logPriority = PropertyLogPriority::NORMAL
    ): self {
        $this->propertyNameToData += $nameToValue;
        return $this;
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs($this->propertyNameToData);
    }
}
