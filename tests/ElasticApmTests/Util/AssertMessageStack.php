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

namespace ElasticApmTests\Util;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\LogStreamInterface;
use Elastic\Apm\Impl\Log\NoopLoggerFactory;
use Elastic\Apm\Impl\Util\ClassicFormatStackTraceFrame;
use Elastic\Apm\Impl\Util\RangeUtil;
use Elastic\Apm\Impl\Util\StackTraceUtil;
use PHPUnit\Framework\Assert;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionParameter;

final class AssertMessageStack implements LoggableInterface
{
    /** @var bool */
    private static $isEnabled = true;

    /** @var ?AssertMessageStack */
    private static $singleton = null;

    /** @var AssertMessageStackScopeData[] */
    private $scopesStack = [];

    public static function setEnabled(bool $isEnabled): void
    {
        self::$isEnabled = $isEnabled;
    }

    private static function ensureSingleton(): self
    {
        if (self::$singleton === null) {
            self::$singleton = new self();
        }
        return self::$singleton;
    }

    /**
     * @param int                         $numberOfStackFramesToSkip
     * @param array<string, mixed>        $initialCtx
     *
     * @return AssertMessageStackScopeAutoRef
     *
     * @phpstan-param 0|positive-int $numberOfStackFramesToSkip
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private function newScopeImpl(int $numberOfStackFramesToSkip, array $initialCtx): AssertMessageStackScopeAutoRef
    {
        $newScopeData = new AssertMessageStackScopeData(AssertMessageStackScopeData::buildContextName($numberOfStackFramesToSkip + 1), $initialCtx);
        $newScope = new AssertMessageStackScopeAutoRef($this, $newScopeData);
        $this->scopesStack[] = $newScopeData;
        return $newScope;
    }

    /**
     * @param ?AssertMessageStackScopeAutoRef   &$scopeVar
     * @param array<string, mixed>               $initialCtx
     *
     * @return void
     *
     * @param-out AssertMessageStackScopeAutoRef $scopeVar
     */
    public static function newScope(/* out */ ?AssertMessageStackScopeAutoRef &$scopeVar, array $initialCtx = []): void
    {
        Assert::assertNull($scopeVar);

        if (!self::$isEnabled) {
            $scopeVar = new AssertMessageStackScopeAutoRef(self::ensureSingleton(), null);
            return;
        }

        $scopeVar = self::ensureSingleton()->newScopeImpl(/* numberOfStackFramesToSkip */ 1, $initialCtx);
    }

    /**
     * @return null|ReflectionParameter[]
     */
    private static function getReflectionParametersForStackFrame(ClassicFormatStackTraceFrame $frame): ?array
    {
        if ($frame->function === null) {
            return null;
        }

        try {
            if ($frame->class === null) {
                $reflFuc = new ReflectionFunction($frame->function);
                return $reflFuc->getParameters();
            }
            /** @var class-string $className */
            $className = $frame->class;
            $reflClass = new ReflectionClass($className);
            $reflMethod = $reflClass->getMethod($frame->function);
            return $reflMethod->getParameters();
        } catch (ReflectionException $ex) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function funcArgs(): array
    {
        $result = [];
        $frames = (new StackTraceUtil(NoopLoggerFactory::singletonInstance()))->captureInClassicFormat(
            1 /* <- offset */,
            1 /* <- maxNumberOfFrames */,
            true /* <- includeElasticApmFrames */,
            true /* <- includeArgs */
        );
        Assert::assertCount(1, $frames);
        $frame = $frames[0];
        Assert::assertNotNull($frame->args);
        $reflParams = self::getReflectionParametersForStackFrame($frame);
        foreach (RangeUtil::generateUpTo(count($frame->args)) as $argIndex) {
            $argName = $reflParams === null || count($reflParams) <= $argIndex ? ('arg #' . ($argIndex + 1)) : $reflParams[$argIndex]->getName();
            $result[$argName] = $frame->args[$argIndex];
        }
        return $result;
    }

    public function autoPopScope(AssertMessageStackScopeData $expectedTopData): void
    {
        $dbgCtx = ['this' => $this, 'expectedTopData' => $expectedTopData];
        Assert::assertNotEmpty($this->scopesStack, LoggableToString::convert($dbgCtx));
        $actualTopData = $this->scopesStack[count($this->scopesStack) - 1];
        Assert::assertSame($expectedTopData, $actualTopData, LoggableToString::convert($dbgCtx));
        array_pop(/* ref */ $this->scopesStack);
    }

    /**
     * @return iterable<Pair<string, array<string, mixed>>>
     */
    private function getContextsStackAsNameCtxPairs(): iterable
    {
        $result = [];
        foreach (RangeUtil::generateDownFrom(count($this->scopesStack)) as $scopeIndex) {
            $scopeData = $this->scopesStack[$scopeIndex];
            foreach (RangeUtil::generateDownFrom(count($scopeData->subScopesStack)) as $subScopeIndex) {
                $result[] = $scopeData->subScopesStack[$subScopeIndex];
            }
        }
        return $result;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getContextsStack(): array
    {
        if (!self::$isEnabled) {
            return [];
        }

        $totalCount =  IterableUtilForTests::count(self::ensureSingleton()->getContextsStackAsNameCtxPairs());
        $result = [];
        $totalIndex = 1;
        foreach (self::ensureSingleton()->getContextsStackAsNameCtxPairs() as $nameCtxPair) {
            $result[($totalIndex++) . ' out of ' . $totalCount . ': ' . $nameCtxPair->first] = $nameCtxPair->second;
        }
        return $result;
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs(['scopesStack count' => count($this->scopesStack), 'isEnabled' => self::$isEnabled]);
    }
}
