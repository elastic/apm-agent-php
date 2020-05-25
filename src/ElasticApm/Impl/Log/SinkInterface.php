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
     * @param int                         $statementLevel
     * @param string                      $message
     * @param array<array<string, mixed>> $contextsStack
     * @param string                      $category
     * @param string                      $srcCodeFile
     * @param int                         $srcCodeLine
     * @param string                      $srcCodeFunc
     * @param int                         $numberOfStackFramesToSkip
     */
    public function consume(
        int $statementLevel,
        string $message,
        array $contextsStack,
        string $category,
        string $srcCodeFile,
        int $srcCodeLine,
        string $srcCodeFunc,
        int $numberOfStackFramesToSkip
    ): void;
}
