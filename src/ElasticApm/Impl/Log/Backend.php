<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Backend
{
    /** @var SinkInterface */
    private $logSink;

    /** @var int */
    private $maxEnabledLevel;

    public function __construct(?SinkInterface $logSink)
    {
        $this->logSink = $logSink ?? new DefaultSink();
        $this->maxEnabledLevel = Level::TRACE;
    }

    public function maxEnabledLevel(): int
    {
        return $this->maxEnabledLevel;
    }

    /**
     * @param int          $statementLevel
     * @param string       $message
     * @param array<mixed> $statementCtx
     * @param int          $srcCodeLine
     * @param string       $srcCodeFunc
     * @param LoggerData   $loggerData
     * @param int          $numberOfStackFramesToSkip
     */
    public function log(
        int $statementLevel,
        string $message,
        array $statementCtx,
        int $srcCodeLine,
        string $srcCodeFunc,
        LoggerData $loggerData,
        int $numberOfStackFramesToSkip
    ): void {
        $this->logSink->consume(
            $statementLevel,
            $message,
            $statementCtx,
            $loggerData->category,
            $loggerData->sourceCodeFile,
            $srcCodeLine,
            $srcCodeFunc,
            $numberOfStackFramesToSkip + 1,
            array_merge(
                $loggerData->attachedCtx,
                ['namespace' => $loggerData->namespace, 'class' => $loggerData->className]
            )
        );
    }
}
