<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

use Elastic\Apm\Impl\Util\TextUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class SinkBase implements SinkInterface
{
    public function consume(
        int $statementLevel,
        string $message,
        array $contextsStack,
        string $category,
        string $srcCodeFile,
        int $srcCodeLine,
        string $srcCodeFunc,
        ?bool $includeStacktrace,
        int $numberOfStackFramesToSkip
    ): void {
        $combinedContext = [];

        // Traverse $contextsStack in reverse order since the data most specific to the log statement is on top
        for (end($contextsStack); key($contextsStack) !== null; prev($contextsStack)) {
            foreach (current($contextsStack) as $key => $value) {
                $combinedContext[$key] = $value;
            }
        }

        if (is_null($includeStacktrace) ? ($statementLevel <= Level::ERROR) : $includeStacktrace) {
            $combinedContext[LoggablePhpStacktrace::STACK_TRACE_KEY]
                = LoggablePhpStacktrace::buildForCurrent($numberOfStackFramesToSkip + 1);
        }

        $afterMessageDelimiter = TextUtil::isSuffixOf('.', $message) ? '' : '.';
        $messageWithContext = $message . $afterMessageDelimiter . ' ' . LoggableToString::convert($combinedContext);

        $this->consumePreformatted(
            $statementLevel,
            $category,
            $srcCodeFile,
            $srcCodeLine,
            $srcCodeFunc,
            $messageWithContext
        );
    }

    /**
     * @param int    $statementLevel
     * @param string $category
     * @param string $srcCodeFile
     * @param int    $srcCodeLine
     * @param string $srcCodeFunc
     * @param string $messageWithContext
     */
    abstract protected function consumePreformatted(
        int $statementLevel,
        string $category,
        string $srcCodeFile,
        int $srcCodeLine,
        string $srcCodeFunc,
        string $messageWithContext
    ): void;
}
