<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Logger
{
    /** @var LoggerData */
    private $data;

    /** @var int */
    private $maxEnabledLevel;

    public function __construct(string $className, string $sourceCodeFile, Backend $backend)
    {
        $this->data = new LoggerData($className, $sourceCodeFile, $backend);
        $this->maxEnabledLevel = $backend->maxEnabledLevel();
    }

    /**
     * @param mixed $value
     */
    public function addKeyValueToAttachedContext(string $key, $value): void
    {
        $this->data->attachedContext[$key] = $value;
    }

    /**
     * @param mixed $value
     */
    public function addValueToAttachedContext($value): void
    {
        $this->data->attachedContext[] = $value;
    }

    public function ifEnabledError(): ?EnabledLoggerProxy
    {
        return $this->ifEnabledLevel(Level::ERROR);
    }

    public function ifEnabledDebug(): ?EnabledLoggerProxy
    {
        return $this->ifEnabledLevel(Level::DEBUG);
    }

    private function ifEnabledLevel(int $statementLevel): ?EnabledLoggerProxy
    {
        return ($this->maxEnabledLevel >= $statementLevel)
            ? new EnabledLoggerProxy($statementLevel, $this->data)
            : null;
    }
}
