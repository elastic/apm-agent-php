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

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument\Util;

use Closure;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Util\Assert;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\SpanInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class AutoInstrumentationUtil
{
    /** @var Logger */
    private $logger;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->loggerForClass(
            LogCategory::AUTO_INSTRUMENTATION,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );
    }

    public static function buildSpanNameFromCall(?string $className, string $funcName): string
    {
        return ($className === null) ? $funcName : ($className . '->' . $funcName);
    }

    /**
     * @param int             $numberOfStackFramesToSkip
     * @param SpanInterface   $span
     * @param bool            $hasExitedByException
     * @param mixed|Throwable $returnValueOrThrown
     * @param ?float          $duration
     */
    public static function endSpan(
        int $numberOfStackFramesToSkip,
        SpanInterface $span,
        bool $hasExitedByException,
        $returnValueOrThrown,
        ?float $duration = null
    ): void {
        if ($hasExitedByException && ($returnValueOrThrown instanceof Throwable)) {
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
     * @param SpanInterface                   $span
     * @param null|Closure(bool, mixed): void $doBeforeSpanEnd
     *
     * @return null|callable(int, bool, mixed): void
     */
    public static function createPostHookFromEndSpan(
        SpanInterface $span,
        ?Closure $doBeforeSpanEnd = null
    ): ?callable {
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
            $span,
            $doBeforeSpanEnd
        ): void {
            if ($doBeforeSpanEnd !== null) {
                $doBeforeSpanEnd($hasExitedByException, $returnValueOrThrown);
            }
            self::endSpan(
                $numberOfStackFramesToSkip + 1,
                $span,
                $hasExitedByException,
                $returnValueOrThrown
            );
        };
    }

    /**
     * @param bool                 $hasExitedByException
     * @param array<string, mixed> $dbgCtx
     *
     * @return void
     */
    public static function assertInterceptedCallNotExitedByException(
        bool $hasExitedByException,
        array $dbgCtx = []
    ): void {
        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that(!$hasExitedByException)
        && $assertProxy->withContext('!$hasExitedByException', $dbgCtx);
    }

    /**
     * @param bool   $isOfExpectedType
     * @param string $dbgExpectedType
     * @param mixed  $dbgActualValue
     *
     * @return bool
     */
    public function verifyType(bool $isOfExpectedType, string $dbgExpectedType, $dbgActualValue): bool
    {
        if ($isOfExpectedType) {
            return true;
        }

        ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Actual type does not match the expected type',
            [
                'expected type' => $dbgExpectedType,
                'actual type'   => DbgUtil::getType($dbgActualValue),
                'actual value'  => $this->logger->possiblySecuritySensitive($dbgActualValue),
            ]
        );
        return false;
    }

    /**
     * @param mixed  $actualValue
     *
     * @return bool
     */
    public function verifyIsString($actualValue): bool
    {
        return $this->verifyType(is_string($actualValue), 'string', $actualValue);
    }

    /**
     * @param mixed  $actualValue
     *
     * @return bool
     */
    public function verifyIsInt($actualValue): bool
    {
        return $this->verifyType(is_int($actualValue), 'int', $actualValue);
    }

    /**
     * @param mixed  $actualValue
     *
     * @return bool
     */
    public function verifyIsBool($actualValue): bool
    {
        return $this->verifyType(is_bool($actualValue), 'bool', $actualValue);
    }

    /**
     * @param mixed  $actualValue
     *
     * @return bool
     */
    public function verifyIsArray($actualValue): bool
    {
        return $this->verifyType(is_array($actualValue), 'array', $actualValue);
    }

    /**
     * @param mixed $actualValue
     *
     * @return bool
     */
    public function verifyIsObject($actualValue): bool
    {
        return $this->verifyType(is_object($actualValue), 'object', $actualValue);
    }

    /**
     * @param class-string $expectedClass
     * @param mixed        $actualValue
     *
     * @return bool
     */
    public function verifyInstanceOf(string $expectedClass, $actualValue): bool
    {
        if ($actualValue === null) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Actual value is null and thus it is not an instance the expected class',
                ['expected class' => $expectedClass]
            );
            return false;
        }

        if (!is_object($actualValue)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Actual value is not an object and thus it is not an instance the expected class',
                [
                    'expected class' => $expectedClass,
                    'actual type'    => DbgUtil::getType($actualValue),
                    'actual value'   => $this->logger->possiblySecuritySensitive($actualValue),
                ]
            );
            return false;
        }

        if (!($actualValue instanceof $expectedClass)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Actual value is not an instance the expected class',
                [
                    'expected class' => $expectedClass,
                    'actual type'    => DbgUtil::getType($actualValue),
                    'actual value'   => $this->logger->possiblySecuritySensitive($actualValue),
                ]
            );
            return false;
        }

        return true;
    }

    /**
     * @param int     $expectedMinNumberOfArgs
     * @param mixed[] $interceptedCallArgs
     *
     * @return bool
     */
    public function verifyMinArgsCount(int $expectedMinNumberOfArgs, array $interceptedCallArgs): bool
    {
        if (count($interceptedCallArgs) >= $expectedMinNumberOfArgs) {
            return true;
        }

        ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Actual number of arguments is less than expected',
            [
                'expected min number of arguments' => $expectedMinNumberOfArgs,
                'actual number of arguments'       => count($interceptedCallArgs),
                'actual arguments'                 => $this->logger->possiblySecuritySensitive($interceptedCallArgs),
            ]
        );
        return false;
    }
}
