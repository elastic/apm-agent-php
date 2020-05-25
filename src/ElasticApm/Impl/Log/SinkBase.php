<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
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
        int $numberOfStackFramesToSkip
    ): void {
        $contextToStringBuilder = new ObjectToStringBuilder();

        foreach ($contextsStack as $context) {
            foreach ($context as $key => $value) {
                $contextToStringBuilder->add($key, $value);
            }
        }

        $messageWithContext = $message . ' ' . $contextToStringBuilder->build();

        if ($statementLevel <= Level::ERROR) {
            $messageWithContext .= PHP_EOL;
            $messageWithContext .= TextUtil::indent('Stack trace:');
            $messageWithContext .= PHP_EOL;
            $messageWithContext .= TextUtil::indent(
                DbgUtil::formatCurrentStackTrace($numberOfStackFramesToSkip + 1),
                /*level: */ 2
            );
        }

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
