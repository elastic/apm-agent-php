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
use Elastic\Apm\Impl\Util\DbgUtil;
use PHPUnit\Framework\Assert;

final class AssertMessageStack implements LoggableInterface
{
    /** @var ?AssertMessageStack */
    private static $singleton = null;

    /** @var AssertMessageStackScopeData[] */
    private $scopesStack = [];

    private static function ensureSingleton(): self
    {
        if (self::$singleton === null) {
            self::$singleton = new self();
        }
        return self::$singleton;
    }

    /**
     * We do not use ArrayUtilForTests because it uses TestCaseBase and TestCaseBase uses this class
     *
     * @template TKey of array-key
     *
     * @param array<TKey, mixed> $array
     *
     * @return array-key
     *
     * @phpstan-return TKey
     */
    private static function getLastKeyInArray(array $array)
    {
        // We use Assert::assert* and not TestCaseBase::assert* because TestCaseBase uses this class

        $dbgCtx = ['array' => $array];
        Assert::assertNotEmpty($array);

        $lastKey = array_key_last($array);
        $dbgCtx['lastKey'] = $lastKey;
        Assert::assertNotNull($lastKey, LoggableToString::convert($dbgCtx));
        Assert::assertArrayHasKey($lastKey, $array, LoggableToString::convert($dbgCtx));

        return $lastKey;
    }

    /**
     * We do not use ArrayUtilForTests::getLastValue because it uses TestCaseBase and TestCaseBase uses this class
     *
     * @template T
     *
     * @param array<array-key, T> $array
     *
     * @return  T
     */
    private static function getLastValueInArray(array $array)
    {
        // We use Assert::assert* and not TestCaseBase::assert* because TestCaseBase uses this class

        $dbgCtx = ['array' => $array];
        Assert::assertNotEmpty($array);

        foreach (array_reverse($array) as $val) {
            return $val;
        }

        Assert::fail(LoggableToString::convert($dbgCtx));
    }

    /** @noinspection PhpSameParameterValueInspection */
    private function newScopeImpl(int $numberOfStackFramesToSkip): AssertMessageStackScope
    {
        $newScopeData = new AssertMessageStackScopeData(self::buildContextName($numberOfStackFramesToSkip + 1));
        $newScope = new AssertMessageStackScope($this, $newScopeData);
        $this->scopesStack[] = $newScopeData;
        return $newScope;
    }

    /**
     * @param ?AssertMessageStackScope   &$scopeVar
     *
     * @return void
     *
     * @param-out AssertMessageStackScope $scopeVar
     */
    public static function newScope(/* out */ ?AssertMessageStackScope &$scopeVar): void
    {
        $scopeVar = self::ensureSingleton()->newScopeImpl(/* numberOfStackFramesToSkip */ 1);
    }

    public static function newSubScope(/* ref */ AssertMessageStackScope &$scopeVar): void
    {
        Assert::assertNotNull($scopeVar);
        $singleton = self::ensureSingleton();
        /** @var AssertMessageStackScopeData $topScope */
        $topScope = self::getLastValueInArray($singleton->scopesStack);
        Assert::assertSame(1, $topScope->refsFromStackCount);
        ++$topScope->refsFromStackCount;
        $scopeVar = self::ensureSingleton()->newScopeImpl(/* numberOfStackFramesToSkip */ 1);
    }

    public static function popSubScope(AssertMessageStackScope &$scopeVar): void
    {
        Assert::assertNotNull($scopeVar);
        $singleton = self::ensureSingleton();
        $scopeToPopKey = self::getLastKeyInArray($singleton->scopesStack);
        $scopeToPop = $singleton->scopesStack[$scopeToPopKey];
        Assert::assertSame(1, $scopeToPop->refsFromStackCount);
        --$scopeToPop->refsFromStackCount;
        unset($singleton->scopesStack[$scopeToPopKey]);
        $scopeVar = new AssertMessageStackScope($singleton, self::getLastValueInArray($singleton->scopesStack));
    }

    public function removeScope(AssertMessageStackScopeData $scopeDataToRemove): void
    {
        $dbgCtx = ['this' => $this, '$scopeDataToRemove' => $scopeDataToRemove];
        Assert::assertNotEmpty($this->scopesStack, LoggableToString::convert($dbgCtx));
        Assert::assertGreaterThan(0, $scopeDataToRemove->refsFromStackCount, LoggableToString::convert($dbgCtx));
        --$scopeDataToRemove->refsFromStackCount;
        if ($scopeDataToRemove->refsFromStackCount !== 0) {
            return;
        }

        foreach ($this->scopesStack as $key => $scopeData) {
            if ($scopeData === $scopeDataToRemove) {
                unset($this->scopesStack[$key]);
                return;
            }
        }
        TestCaseBase::fail('Scope data to remove was not found; ' . LoggableToString::convert($dbgCtx));
    }

    /**
     * @return AssertMessageStackScopeData[]
     */
    public static function getScopeDataStack(): array
    {
        return array_reverse(self::ensureSingleton()->scopesStack);
    }

    private function formatScopesStackAsStringImpl(): string
    {
        $result = [];
        foreach (array_reverse($this->scopesStack) as $scopeData) {
            $result[$scopeData->name] = $scopeData->ctx;
        }
        return LoggableToString::convert($result, /* prettyPrint */ true);
    }

    public static function formatScopesStackAsString(): string
    {
        return self::ensureSingleton()->formatScopesStackAsStringImpl();
    }

    /**
     * @noinspection PhpSameParameterValueInspection
     */
    private static function buildContextName(int $numberOfStackFramesToSkip): string
    {
        $callerInfo = DbgUtil::getCallerInfoFromStacktrace($numberOfStackFramesToSkip + 1);

        $classMethodPart = '';
        if ($callerInfo->class !== null) {
            $classMethodPart .= $callerInfo->class . '::';
        }
        Assert::assertNotNull($callerInfo->function);
        $classMethodPart .= $callerInfo->function;

        $fileLinePart = '';
        if ($callerInfo->file !== null) {
            $fileLinePart .= '[';
            $fileLinePart .= $callerInfo->file;
            $fileLinePart .= TextUtilForTests::combineWithSeparatorIfNotEmpty(':', TextUtilForTests::emptyIfNull($callerInfo->line));
            $fileLinePart .= ']';
        }

        return $classMethodPart . TextUtilForTests::combineWithSeparatorIfNotEmpty(' ', $fileLinePart);
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs($this->scopesStack);
    }
}
