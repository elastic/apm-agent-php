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
use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Span;
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

    private static function processNewSpan(SpanInterface $span): void
    {
        if ($span instanceof Span) {
            // Mark all spans created by auto-instrumentation as compressible
            $span->setCompressible(true);
        }
    }

    public static function beginCurrentSpan(string $name, string $type, ?string $subtype = null, ?string $action = null): SpanInterface
    {
        $span = ElasticApm::getCurrentTransaction()->beginCurrentSpan($name, $type, $subtype, $action);

        self::processNewSpan($span);

        return $span;
    }

    /**
     * @param string   $name
     * @param string   $type
     * @param ?string  $subtype
     * @param ?string  $action
     * @param callable $callback
     * @param mixed[]  $callbackArgs
     * @param int      $numberOfStackFramesToSkip
     *
     * @return mixed
     *
     * @phpstan-param 0|positive-int $numberOfStackFramesToSkip
     */
    public static function captureCurrentSpan(string $name, string $type, ?string $subtype, ?string $action, callable $callback, array $callbackArgs, int $numberOfStackFramesToSkip)
    {
        $span = self::beginCurrentSpan($name, $type, $subtype, $action);
        try {
            return call_user_func_array($callback, $callbackArgs);
        } catch (Throwable $throwable) {
            $span->createErrorFromThrowable($throwable);
            throw $throwable;
        } finally {
            $span->endSpanEx($numberOfStackFramesToSkip + 1);
        }
    }

    /**
     * @param int             $numberOfStackFramesToSkip
     * @param SpanInterface   $span
     * @param bool            $hasExitedByException
     * @param mixed|Throwable $returnValueOrThrown
     * @param ?float          $duration
     *
     * @phpstan-param 0|positive-int $numberOfStackFramesToSkip
     */
    public static function endSpan(int $numberOfStackFramesToSkip, SpanInterface $span, bool $hasExitedByException, $returnValueOrThrown, ?float $duration = null): void
    {
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
    public static function createInternalFuncPostHookFromEndSpan(SpanInterface $span, ?Closure $doBeforeSpanEnd = null): ?callable
    {
        if ($span->isNoop()) {
            return null;
        }

        /**
         * @param int   $numberOfStackFramesToSkip
         * @param bool  $hasExitedByException
         * @param mixed $returnValueOrThrown Return value of the intercepted call or thrown object
         *
         * @phpstan-param 0|positive-int $numberOfStackFramesToSkip
         */
        return function (int $numberOfStackFramesToSkip, bool $hasExitedByException, $returnValueOrThrown) use ($span, $doBeforeSpanEnd): void {
            if ($doBeforeSpanEnd !== null) {
                $doBeforeSpanEnd($hasExitedByException, $returnValueOrThrown);
            }
            /** @var 0|positive-int $numberOfStackFramesToSkip */
            self::endSpan($numberOfStackFramesToSkip + 1, $span, $hasExitedByException, $returnValueOrThrown);
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
     * @param bool    $isOfExpectedType
     * @param string  $dbgExpectedType
     * @param mixed   $dbgActualValue
     * @param ?string $dbgParamName
     *
     * @return bool
     */
    public function verifyType(bool $isOfExpectedType, string $dbgExpectedType, $dbgActualValue, ?string $dbgParamName = null): bool
    {
        if ($isOfExpectedType) {
            return true;
        }

        $ctx = [
            'expected type' => $dbgExpectedType,
            'actual type'   => DbgUtil::getType($dbgActualValue),
            'actual value'  => $this->logger->possiblySecuritySensitive($dbgActualValue),
        ];
        if ($dbgParamName !== null) {
            $ctx = array_merge(['parameter name' => $dbgParamName], $ctx);
        }

        ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->includeStackTrace()->log('Actual type does not match the expected type', $ctx);
        return false;
    }

    /**
     * @param mixed   $actualValue
     * @param ?string $dbgParamName
     *
     * @return bool
     */
    public function verifyIsString($actualValue, ?string $dbgParamName = null): bool
    {
        return $this->verifyType(is_string($actualValue), 'string', $actualValue, $dbgParamName);
    }

    /**
     * @param mixed   $actualValue
     * @param ?string $dbgParamName
     *
     * @return bool
     */
    public function verifyIsInt($actualValue, ?string $dbgParamName = null): bool
    {
        return $this->verifyType(is_int($actualValue), 'int', $actualValue, $dbgParamName);
    }

    /**
     * @param mixed   $actualValue
     * @param ?string $dbgParamName
     *
     * @return bool
     */
    public function verifyIsBool($actualValue, ?string $dbgParamName = null): bool
    {
        return $this->verifyType(is_bool($actualValue), 'bool', $actualValue, $dbgParamName);
    }

    /**
     * @param mixed   $actualValue
     * @param ?string $dbgParamName
     *
     * @return bool
     */
    public function verifyIsArray($actualValue, ?string $dbgParamName = null): bool
    {
        return $this->verifyType(is_array($actualValue), 'array', $actualValue, $dbgParamName);
    }

    /**
     * @param mixed   $actualValue
     * @param ?string $dbgParamName
     *
     * @return bool
     */
    public function verifyIsObject($actualValue, ?string $dbgParamName = null): bool
    {
        return $this->verifyType(is_object($actualValue), 'object', $actualValue, $dbgParamName);
    }

    /**
     * @param class-string $expectedClass
     * @param mixed        $actualValue
     * @param ?string      $dbgParamName
     *
     * @return bool
     */
    public function verifyInstanceOf(string $expectedClass, $actualValue, ?string $dbgParamName = null): bool
    {
        if (!$this->verifyIsObject($actualValue, $dbgParamName)) {
            return false;
        }
        if (!($actualValue instanceof $expectedClass)) {
            $ctx = [
                'expected class' => $expectedClass,
                'actual type'    => DbgUtil::getType($actualValue),
                'actual value'   => $this->logger->possiblySecuritySensitive($actualValue),
            ];
            if ($dbgParamName !== null) {
                $ctx = array_merge(['parameter name' => $dbgParamName], $ctx);
            }

            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Actual value is not an instance the expected class', $ctx);
            return false;
        }

        return true;
    }

    /**
     * @param mixed   $actualValue
     * @param bool    $shouldCheckSyntaxOnly
     * @param ?string $dbgParamName
     *
     * @return bool
     */
    public function verifyIsCallable($actualValue, bool $shouldCheckSyntaxOnly, ?string $dbgParamName = null): bool
    {
        $isCallable = is_callable($actualValue, $shouldCheckSyntaxOnly);
        return $this->verifyType($isCallable, 'callable', $actualValue, $dbgParamName);
    }

    /**
     * @param int     $expectedMinArgsCount
     * @param mixed[] $interceptedCallArgs
     *
     * @return bool
     */
    public function verifyMinArgsCount(int $expectedMinArgsCount, array $interceptedCallArgs): bool
    {
        if (count($interceptedCallArgs) >= $expectedMinArgsCount) {
            return true;
        }

        ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Actual number of arguments is less than expected',
            [
                'expected minimal number of arguments' => $expectedMinArgsCount,
                'actual number of arguments'           => count($interceptedCallArgs),
                'actual arguments'                     => $this->logger->possiblySecuritySensitive($interceptedCallArgs),
            ]
        );
        return false;
    }

    /**
     * @param int     $expectedArgsCount
     * @param mixed[] $interceptedCallArgs
     *
     * @return bool
     */
    public function verifyExactArgsCount(int $expectedArgsCount, array $interceptedCallArgs): bool
    {
        if (count($interceptedCallArgs) === $expectedArgsCount) {
            return true;
        }

        ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Actual number of arguments does not equal the expected number',
            [
                'expected number of arguments' => $expectedArgsCount,
                'actual number of arguments'   => count($interceptedCallArgs),
                'actual arguments'             => $this->logger->possiblySecuritySensitive($interceptedCallArgs),
            ]
        );
        return false;
    }
}
