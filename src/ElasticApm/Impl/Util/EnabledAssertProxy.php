<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class EnabledAssertProxy
{
    public function that(bool $condition): bool
    {
        // Return the opposite so that $assertProxy->info() is invoked and throws AssertException
        //
        //         ($assertProxy = Assert::ifEnabled())
        //         && $assertProxy->that($condition)
        //         && $assertProxy->info(...);

        return !$condition;
    }

    /**
     * @param string               $conditionAsString
     * @param array<string, mixed> $context
     *
     * @return bool
     * @throws AssertException
     */
    public function withContext(string $conditionAsString, array $context): bool
    {
        $callerInfo = DbgUtil::getCallerInfoFromStacktrace(/* numberOfStackFramesToSkip: */ 1);
        throw new AssertException(
            ExceptionUtil::buildMessage(
                'Assertion failed',
                ['condition' => $conditionAsString, 'location' => $callerInfo] + $context
            )
        );
    }
}
