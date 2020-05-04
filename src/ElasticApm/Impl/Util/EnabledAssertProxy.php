<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

use Elastic\Apm\Impl\Log\Backend as LogBackend;
use Elastic\Apm\Impl\Log\Level as LogLevel;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class EnabledAssertProxy
{
    /** @var int */
    private $statementLevel;

    public function __construct(int $statementLevel)
    {
        $this->statementLevel = $statementLevel;
    }

    public function that(bool $condition): bool
    {
        // Return the opposite so that $assertProxy->info() is invoked and throws AssertException
        //
        //         ($assertProxy = Assert::ifEnabled())
        //         && $assertProxy->that( $condition )
        //         && $assertProxy->info( ... );

        return !$condition;
    }

    /**
     * @param string               $conditionAsString
     * @param array<string, mixed> $context
     *
     * @return bool
     * @throws AssertException
     */
    public function info(string $conditionAsString, array $context): bool
    {
        $numberOfStackFramesToSkip = 1;
        $callerInfo = DbgUtil::getCallerInfoFromStacktrace($numberOfStackFramesToSkip);
        $sourceCodeFunc = is_null($callerInfo->class) ? '' : $callerInfo->class . '::';
        $sourceCodeFunc .= $callerInfo->function;
        LogBackend::logEx(
            LogLevel::CRITICAL,
            "Assertion $conditionAsString failed",
            $context,
            $callerInfo->file,
            $callerInfo->line,
            $sourceCodeFunc,
            $numberOfStackFramesToSkip
        );

        $contextToStringBuilder = new ObjectToStringBuilder();
        foreach ($context as $key => $value) {
            $contextToStringBuilder->add($key, $value);
        }
        throw new AssertException(
            "Assertion $conditionAsString failed. "
            . $contextToStringBuilder->build()
        );
    }
}
