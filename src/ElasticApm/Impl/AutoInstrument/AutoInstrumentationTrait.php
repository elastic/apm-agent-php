<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Util\Assert;
use Elastic\Apm\SpanInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait AutoInstrumentationTrait
{
    use LoggableTrait;

    /**
     * @param int             $numberOfStackFramesToSkip
     * @param SpanInterface   $span
     * @param bool            $hasExitedByException
     * @param mixed|Throwable $returnValueOrThrown Return value of the intercepted call or thrown object
     * @param ?float          $duration
     */
    protected static function endSpan(
        int $numberOfStackFramesToSkip,
        SpanInterface $span,
        bool $hasExitedByException,
        $returnValueOrThrown,
        ?float $duration = null
    ): void {
        if ($hasExitedByException && is_object($returnValueOrThrown) && ($returnValueOrThrown instanceof Throwable)) {
            $span->createErrorFromThrowable($returnValueOrThrown);
        }
        // endSpanEx() is a public API so by default it will appear on the stacktrace
        // because it assumes that it was called by the application and
        // it is important to know from where in the application.
        // But in this case endSpanEx() is called by agent's code so there's no point for it
        // to appear on the stack trace.
        // That is the reason to +1 to the usual $numberOfStackFramesToSkip + 1
        $span->endSpanEx($numberOfStackFramesToSkip + 2, $duration);
    }

    /**
     * @param SpanInterface $span
     *
     * @return callable
     * @phpstan-return callable(int, bool, mixed): void
     */
    protected static function createPostHookFromEndSpan(SpanInterface $span): ?callable
    {
        if ($span->isNoop()) {
            return null;
        }

        /**
         * @param int   $numberOfStackFramesToSkip
         * @param bool  $hasExitedByException
         * @param mixed $returnValueOrThrown Return value of the intercepted call or thrown object
         */
        return function (
            int $numberOfStackFramesToSkip,
            bool $hasExitedByException,
            $returnValueOrThrown
        ) use (
            $span
        ): void {
            self::endSpan(
                $numberOfStackFramesToSkip + 1,
                $span,
                $hasExitedByException,
                $returnValueOrThrown
            );
        };
    }

    /**
     * @param object|null $interceptedCallThis
     * @param mixed[]     $interceptedCallArgs Intercepted call arguments
     *
     * @return void
     */
    protected static function assertInterceptedCallThisIsNotNull(
        ?object $interceptedCallThis,
        array $interceptedCallArgs
    ): void {
        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that($interceptedCallThis !== null)
        && $assertProxy->withContext('$interceptedCallThis !== null', ['interceptedCallArgs' => $interceptedCallArgs]);
    }

    /**
     * @param bool                 $hasExitedByException
     * @param array<string, mixed> $dbgCtx
     *
     * @return void
     */
    protected static function assertInterceptedCallNotExitedByException(
        bool $hasExitedByException,
        array $dbgCtx = []
    ): void {
        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that(!$hasExitedByException)
        && $assertProxy->withContext('!$hasExitedByException', $dbgCtx);
    }
}
