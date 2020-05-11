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

    public function __construct(
        string $category,
        string $namespace,
        string $className,
        string $sourceCodeFile,
        Backend $backend
    ) {
        $this->data = new LoggerData($category, $namespace, $className, $sourceCodeFile, $backend);
        $this->maxEnabledLevel = $backend->maxEnabledLevel();
    }

    /**
     * @param mixed $value
     */
    public function addKeyValueToAttachedContext(string $key, $value): void
    {
        $this->data->attachedCtx[$key] = $value;
    }

    /**
     * @param mixed $value
     */
    public function addValueToAttachedContext($value): void
    {
        $this->data->attachedCtx[] = $value;
    }

    public function ifCriticalLevelEnabled(int $srcCodeLine, string $srcCodeFunc): ?EnabledLoggerProxy
    {
        return $this->ifLevelEnabled(Level::CRITICAL, $srcCodeLine, $srcCodeFunc);
    }

    public function ifErrorLevelEnabled(int $srcCodeLine, string $srcCodeFunc): ?EnabledLoggerProxy
    {
        return $this->ifLevelEnabled(Level::ERROR, $srcCodeLine, $srcCodeFunc);
    }

    public function ifWarningLevelEnabled(int $srcCodeLine, string $srcCodeFunc): ?EnabledLoggerProxy
    {
        return $this->ifLevelEnabled(Level::WARNING, $srcCodeLine, $srcCodeFunc);
    }

    public function ifNoticeLevelEnabled(int $srcCodeLine, string $srcCodeFunc): ?EnabledLoggerProxy
    {
        return $this->ifLevelEnabled(Level::NOTICE, $srcCodeLine, $srcCodeFunc);
    }

    public function ifInfoLevelEnabled(int $srcCodeLine, string $srcCodeFunc): ?EnabledLoggerProxy
    {
        return $this->ifLevelEnabled(Level::INFO, $srcCodeLine, $srcCodeFunc);
    }

    public function ifDebugLevelEnabled(int $srcCodeLine, string $srcCodeFunc): ?EnabledLoggerProxy
    {
        return $this->ifLevelEnabled(Level::DEBUG, $srcCodeLine, $srcCodeFunc);
    }

    public function ifTraceLevelEnabled(int $srcCodeLine, string $srcCodeFunc): ?EnabledLoggerProxy
    {
        return $this->ifLevelEnabled(Level::TRACE, $srcCodeLine, $srcCodeFunc);
    }

    private function ifLevelEnabled(int $statementLevel, int $srcCodeLine, string $srcCodeFunc): ?EnabledLoggerProxy
    {
        return ($this->maxEnabledLevel >= $statementLevel)
            ? new EnabledLoggerProxy($statementLevel, $srcCodeLine, $srcCodeFunc, $this->data)
            : null;
    }
}
