<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class EnabledLoggerProxy
{
    /** @var int */
    private $statementLevel;

    /** @var LoggerData */
    private $loggerData;

    public function __construct(int $statementLevel, LoggerData $loggerData)
    {
        $this->statementLevel = $statementLevel;
        $this->loggerData = $loggerData;
    }

    /**
     * @param string       $message
     * @param array<mixed> $context
     * @param int          $sourceCodeLine
     * @param string       $sourceCodeMethod
     */
    public function log(string $message, array $context, int $sourceCodeLine, string $sourceCodeMethod): bool
    {
        $this->loggerData->backend->log(
            $this->statementLevel,
            $message,
            $context,
            $sourceCodeLine,
            $sourceCodeMethod,
            $this->loggerData,
            /* numberOfStackFramesToSkip */ 1
        );
        // return dummy bool to suppress PHPStan's "Right side of && is always false"
        return true;
    }
}
