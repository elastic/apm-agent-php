<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class EnabledLoggerProxy
{
    /** @var int */
    private $statementLevel;

    /** @var int */
    private $srcCodeLine;

    /** @var string */
    private $srcCodeFunc;

    /** @var LoggerData */
    private $loggerData;

    public function __construct(int $statementLevel, int $srcCodeLine, string $srcCodeFunc, LoggerData $loggerData)
    {
        $this->statementLevel = $statementLevel;
        $this->srcCodeLine = $srcCodeLine;
        $this->srcCodeFunc = $srcCodeFunc;
        $this->loggerData = $loggerData;
    }

    /**
     * @param string               $message
     * @param array<string, mixed> $statementCtx
     */
    public function log(string $message, array $statementCtx = []): bool
    {
        $this->loggerData->backend->log(
            $this->statementLevel,
            $message,
            $statementCtx,
            $this->srcCodeLine,
            $this->srcCodeFunc,
            $this->loggerData,
            /* numberOfStackFramesToSkip */ 1
        );
        // return dummy bool to suppress PHPStan's "Right side of && is always false"
        return true;
    }

    /**
     * @param Throwable            $throwable
     * @param string               $message
     * @param array<string, mixed> $statementCtx
     *
     * @return bool
     */
    public function logThrowable(Throwable $throwable, string $message, array $statementCtx = []): bool
    {
        $this->loggerData->backend->log(
            $this->statementLevel,
            $message,
            $statementCtx + ['throwable' => $throwable],
            $this->srcCodeLine,
            $this->srcCodeFunc,
            $this->loggerData,
            /* numberOfStackFramesToSkip */ 1
        );
        // return dummy bool to suppress PHPStan's "Right side of && is always false"
        return true;
    }
}
