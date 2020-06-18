<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
interface SinkInterface
{
    /**
     * @param int          $statementLevel
     * @param string       $message
     * @param array<mixed> $statementCtx
     * @param string       $category
     * @param string       $sourceCodeFile
     * @param int          $srcCodeLine
     * @param string       $srcCodeFunc
     * @param int          $numberOfStackFramesToSkip
     * @param array<mixed> $attachedCtx
     */
    public static function consume(
        int $statementLevel,
        string $message,
        array $statementCtx,
        string $category,
        string $sourceCodeFile,
        int $srcCodeLine,
        string $srcCodeFunc,
        int $numberOfStackFramesToSkip,
        array $attachedCtx = []
    ): void;
}
