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

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\AutoInstrument\AutoInstrumentationBase;
use Elastic\Apm\Impl\Config\AllOptionsMetadata;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\TransactionContext;
use Elastic\Apm\Impl\Util\BoolUtil;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\RangeUtil;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataFromAgent;
use ElasticApmTests\Util\IterableUtilForTests;
use ElasticApmTests\Util\MixedMap;
use ElasticApmTests\Util\TestCaseBase;
use ElasticApmTests\Util\TransactionContextDto;
use ElasticApmTests\Util\TransactionDto;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Throwable;

class ComponentTestCaseBase extends TestCaseBase
{
    /** @var ?TestCaseHandle */
    private $testCaseHandle = null;

    public const LOG_LEVEL_FOR_PROD_CODE_KEY = 'log_level_for_prod_code';
    public const LOG_LEVEL_FOR_TEST_CODE_KEY = 'log_level_for_test_code';
    protected const LOG_LEVEL_FOR_CODE_KEYS = [self::LOG_LEVEL_FOR_PROD_CODE_KEY, self::LOG_LEVEL_FOR_TEST_CODE_KEY];

    protected function initTestCaseHandle(?int $escalatedLogLevelForProdCode = null): TestCaseHandle
    {
        if ($this->testCaseHandle !== null) {
            return $this->testCaseHandle;
        }
        ComponentTestsPhpUnitExtension::initSingletons();
        $this->testCaseHandle = new TestCaseHandle($escalatedLogLevelForProdCode, $this->isSpanCompressionCompatible());
        return $this->testCaseHandle;
    }

    protected function getTestCaseHandle(): TestCaseHandle
    {
        return $this->initTestCaseHandle();
    }

    /** @inheritDoc */
    public function tearDown(): void
    {
        if ($this->testCaseHandle !== null) {
            $this->testCaseHandle->tearDown();
            $this->testCaseHandle = null;
        }

        parent::tearDown();
    }

    /**
     * Sub-classes should override this method to return false
     * in order to disable Span Compression feature and have all the expected spans individually.
     *
     * @return bool
     */
    protected function isSpanCompressionCompatible(): bool
    {
        return true;
    }

    public static function appCodeEmpty(): void
    {
    }

    public static function getTracerFromAppCode(): Tracer
    {
        $tracer = GlobalTracerHolder::getValue();
        TestCase::assertInstanceOf(Tracer::class, $tracer);
        /** @var Tracer $tracer */
        return $tracer;
    }

    protected static function buildResourcesClientForAppCode(): ResourcesClient
    {
        $resCleanerId = AmbientContextForTests::testConfig()->dataPerProcess->resourcesCleanerSpawnedProcessInternalId;
        TestCase::assertNotNull($resCleanerId);
        $resCleanerPort = AmbientContextForTests::testConfig()->dataPerProcess->resourcesCleanerPort;
        TestCase::assertNotNull($resCleanerPort);
        return new ResourcesClient($resCleanerId, $resCleanerPort);
    }

    /**
     * @param string                     $optName
     * @param null|string|int|float|bool $optVal
     *
     * @return DataFromAgent
     */
    protected function configTestImpl(string $optName, $optVal): DataFromAgent
    {
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($optName, $optVal): void {
                if ($optVal !== null) {
                    $appCodeParams->setAgentOption($optName, $optVal);
                }
            }
        );
        $appCodeHost->sendRequest(AppCodeTarget::asRouted([__CLASS__, 'appCodeEmpty']));
        return $this->waitForOneEmptyTransaction($testCaseHandle);
    }

    public static function isSmoke(): bool
    {
        ComponentTestsPhpUnitExtension::initSingletons();
        return AmbientContextForTests::testConfig()->isSmoke();
    }

    public static function isMainAppCodeHostHttp(): bool
    {
        ComponentTestsPhpUnitExtension::initSingletons();
        return AmbientContextForTests::testConfig()->appCodeHostKind()->isHttp();
    }

    protected function skipIfMainAppCodeHostIsNotCliScript(): bool
    {
        if (self::isMainAppCodeHostHttp()) {
            self::dummyAssert();
            return true;
        }

        return false;
    }

    protected function skipIfMainAppCodeHostIsNotHttp(): bool
    {
        if (!self::isMainAppCodeHostHttp()) {
            self::dummyAssert();
            return true;
        }

        return false;
    }

    protected function waitForOneEmptyTransaction(TestCaseHandle $testCaseHandle): DataFromAgentPlusRaw
    {
        $dataFromAgent = $testCaseHandle->waitForDataFromAgent((new ExpectedEventCounts())->transactions(1));
        $this->verifyOneTransactionNoSpans($dataFromAgent);
        return $dataFromAgent;
    }

    protected function verifyOneTransactionNoSpans(DataFromAgent $dataFromAgent): TransactionDto
    {
        $this->assertEmpty($dataFromAgent->idToSpan);

        $tx = $dataFromAgent->singleTransaction();
        $this->assertSame(0, $tx->startedSpansCount);
        $this->assertSame(0, $tx->droppedSpansCount);
        $this->assertNull($tx->parentId);
        return $tx;
    }

    /**
     * @param class-string<AutoInstrumentationBase> $instrClassName
     * @param string[]                              $expectedNames
     *
     * @return void
     */
    protected static function implTestIsAutoInstrumentationEnabled(string $instrClassName, array $expectedNames): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['instrClassName' => $instrClassName, 'expectedNames' => $expectedNames]);

        /** @var AutoInstrumentationBase $instr */
        $instr = new $instrClassName(self::buildTracerForTests()->build());
        $actualNames = $instr->keywords();
        $actualNames[] = $instr->name();
        self::assertEqualAsSets($expectedNames, $actualNames);
        $astProcessEnabledDefaultValue = AllOptionsMetadata::get()[OptionNames::AST_PROCESS_ENABLED]->defaultValue();
        $isEnabledByDefault = $astProcessEnabledDefaultValue || (!$instr->requiresUserlandCodeInstrumentation());
        self::assertSame($isEnabledByDefault, $instr->isEnabled());

        /**
         * @param string $name
         *
         * @return iterable<string>
         */
        $genDisabledVariants = function (string $name): iterable {
            yield $name;
            yield '*' . $name;
            yield $name . '*';
            yield '*' . $name . '*';
            yield '*someOtherDummyInstrumentationA*, ' . $name;
            yield $name . ', *someOtherDummyInstrumentationB*';
            yield '*someOtherDummyInstrumentationA*, ' . $name . ', *someOtherDummyInstrumentationB*';
        };

        foreach ($expectedNames as $name) {
            $dbgCtx->pushSubScope();
            foreach ($genDisabledVariants($name) as $disableInstrumentationsOptVal) {
                $dbgCtx->clearCurrentSubScope(['name' => $name, 'disableInstrumentationsOptVal' => $disableInstrumentationsOptVal]);
                $tracer = self::buildTracerForTests()->withConfig(OptionNames::DISABLE_INSTRUMENTATIONS, $disableInstrumentationsOptVal)->build();
                $instr = new $instrClassName($tracer);
                self::assertFalse($instr->isEnabled());
            }
            $dbgCtx->popSubScope();
        }

        /**
         * @return iterable<string>
         */
        $genEnabledVariants = function (): iterable {
            yield '*someOtherDummyInstrumentation*';
            yield '*someOtherDummyInstrumentationA*,  *someOtherDummyInstrumentationB*';
        };

        $dbgCtx->pushSubScope();
        foreach ($genEnabledVariants() as $disableInstrumentationsOptVal) {
            $dbgCtx->clearCurrentSubScope(['disableInstrumentationsOptVal' => $disableInstrumentationsOptVal]);
            $tracer = self::buildTracerForTests()->withConfig(OptionNames::DISABLE_INSTRUMENTATIONS, $disableInstrumentationsOptVal)->build();
            $instr = new $instrClassName($tracer);
            self::assertSame($isEnabledByDefault, $instr->isEnabled());
        }
        $dbgCtx->popSubScope();

        $dbgCtx->pushSubScope();
        foreach ([true, false] as $astProcessEnabled) {
            $dbgCtx->clearCurrentSubScope(['astProcessEnabled' => $astProcessEnabled]);
            $isEnabledByDefault = $astProcessEnabled || (!$instr->requiresUserlandCodeInstrumentation());
            $tracer = self::buildTracerForTests()->withConfig(OptionNames::AST_PROCESS_ENABLED, BoolUtil::toString($astProcessEnabled))->build();
            $instr = new $instrClassName($tracer);
            self::assertSame($isEnabledByDefault, $instr->isEnabled());
        }
        $dbgCtx->popSubScope();
    }

    /**
     * @template T
     *
     * @param iterable<T> $variants
     *
     * @return iterable<T>
     */
    public static function adaptToSmoke(iterable $variants): iterable
    {
        if (!self::isSmoke()) {
            return $variants;
        }
        foreach ($variants as $key => $value) {
            return [$key => $value];
        }
        return [];
    }

    /**
     * @return callable(iterable<mixed>): iterable<mixed>
     */
    public static function adaptToSmokeAsCallable(): callable
    {
        /**
         * @template T
         *
         * @param iterable<T> $dataSets
         *
         * @return iterable<T>
         */
        return function (iterable $dataSets): iterable {
            return self::adaptToSmoke($dataSets);
        };
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param iterable<TKey, TValue> $variants
     *
     * @return iterable<TKey, TValue>
     */
    public function adaptKeyValueToSmoke(iterable $variants): iterable
    {
        if (!self::isSmoke()) {
            return $variants;
        }
        foreach ($variants as $key => $value) {
            return [$key => $value];
        }
        return [];
    }

    /**
     * @return iterable<array{bool}>
     */
    public function boolDataProviderAdaptedToSmoke(): iterable
    {
        return self::adaptToSmoke(self::boolDataProvider());
    }

    /**
     * @param string           $dbgTestDesc
     * @param callable(): void $testCall
     *
     * @return void
     */
    protected function runAndEscalateLogLevelOnFailure(string $dbgTestDesc, callable $testCall): void
    {
        $logLevelForTestCodeToRestore = AmbientContextForTests::testConfig()->logLevel;
        try {
            $this->runAndEscalateLogLevelOnFailureImpl($dbgTestDesc, $testCall);
        } finally {
            AmbientContextForTests::resetLogLevel($logLevelForTestCodeToRestore);
        }
    }

    /**
     * @param string           $dbgTestDesc
     * @param callable(): void $testCall
     *
     * @return void
     */
    private function runAndEscalateLogLevelOnFailureImpl(string $dbgTestDesc, callable $testCall): void
    {
        try {
            $testCall();
            return;
        } catch (Throwable $ex) {
            $initiallyFailedTestException = $ex;
        }

        $logger = $this->getLogger(__NAMESPACE__, __CLASS__, __FILE__)->addContext('dbgTestDesc', $dbgTestDesc);
        $loggerProxyOutsideIt = $logger->ifCriticalLevelEnabledNoLine(__FUNCTION__);
        if ($this->testCaseHandle === null) {
            $loggerProxyOutsideIt && $loggerProxyOutsideIt->log(__LINE__, 'Test failed but $this->testCaseHandle is null - NOT re-running the test with escalated log levels');
            throw $initiallyFailedTestException;
        }
        $initiallyFailedTestLogLevels = $this->getCurrentLogLevels($this->testCaseHandle);
        $logger->addAllContext(['initiallyFailedTestLogLevels' => self::logLevelsWithNames($initiallyFailedTestLogLevels), 'initiallyFailedTestException' => $initiallyFailedTestException]);
        $loggerProxyOutsideIt && $loggerProxyOutsideIt->log(__LINE__, 'Test failed');

        $escalatedLogLevelsSeq = self::generateLevelsForRunAndEscalateLogLevelOnFailure($initiallyFailedTestLogLevels, AmbientContextForTests::testConfig()->escalatedRerunsMaxCount);
        $rerunCount = 0;
        foreach ($escalatedLogLevelsSeq as $escalatedLogLevels) {
            $this->tearDown();

            ++$rerunCount;
            $loggerPerIt = $logger->inherit()->addAllContext(['rerunCount' => $rerunCount, 'escalatedLogLevels' => self::logLevelsWithNames($escalatedLogLevels)]);
            $loggerProxyPerIt = $loggerPerIt->ifCriticalLevelEnabledNoLine(__FUNCTION__);

            $loggerProxyPerIt && $loggerProxyPerIt->log(__LINE__, 'Re-running failed test with escalated log levels...');

            AmbientContextForTests::resetLogLevel($escalatedLogLevels[self::LOG_LEVEL_FOR_TEST_CODE_KEY]);
            $this->initTestCaseHandle($escalatedLogLevels[self::LOG_LEVEL_FOR_PROD_CODE_KEY]);

            try {
                $testCall();
                $loggerProxyPerIt && $loggerProxyPerIt->log(__LINE__, 'Re-run of failed test with escalated log levels did NOT fail (which is bad :(');
            } catch (Throwable $ex) {
                $loggerProxyPerIt && $loggerProxyPerIt->log(__LINE__, 'Re-run of failed test with escalated log levels failed (which is good :)', ['ex' => $ex]);
                throw $ex;
            }
        }

        if ($rerunCount === 0) {
            $loggerProxyOutsideIt && $loggerProxyOutsideIt->log(__LINE__, 'There were no test re-runs with escalated log levels - re-throwing original test failure exception');
        } else {
            $loggerProxyOutsideIt && $loggerProxyOutsideIt->log(__LINE__, 'All test re-runs with escalated log levels did NOT fail (which is bad :( - re-throwing original test failure exception');
        }
        throw $initiallyFailedTestException;
    }

    /**
     * @param class-string $testClass
     * @param string       $testFunc
     *
     * @return string
     */
    protected static function buildDbgDescForTest(string $testClass, string $testFunc): string
    {
        return ClassNameUtil::fqToShort($testClass) . '::' . $testFunc;
    }

    /**
     * @param class-string $testClass
     * @param string       $testFunc
     * @param MixedMap     $testArgs
     *
     * @return string
     */
    protected static function buildDbgDescForTestWithArtgs(string $testClass, string $testFunc, MixedMap $testArgs): string
    {
        return ClassNameUtil::fqToShort($testClass) . '::' . $testFunc . '(' . LoggableToString::convert($testArgs) . ')';
    }

    /**
     * @param TestCaseHandle $testCaseHandle
     *
     * @return array<string, int>
     */
    private function getCurrentLogLevels(TestCaseHandle $testCaseHandle): array
    {
        $result = [];
        $prodCodeLogLevels = $testCaseHandle->getProdCodeLogLevels();
        Assert::assertNotEmpty($prodCodeLogLevels);
        $result[self::LOG_LEVEL_FOR_PROD_CODE_KEY] = min($prodCodeLogLevels);
        $result[self::LOG_LEVEL_FOR_TEST_CODE_KEY] = AmbientContextForTests::testConfig()->logLevel;
        /** @var array<string, int> $result */
        return $result;
    }

    /**
     * @param array<string, int> $initialLevels
     *
     * @return iterable<array<string, int>>
     */
    public static function generateEscalatedLogLevels(array $initialLevels): iterable
    {
        Assert::assertNotEmpty($initialLevels);

        /**
         * @param array<string, int> $currentLevels
         *
         * @return bool
         */
        $haveCurrentLevelsReachedInitial = function (array $currentLevels) use ($initialLevels): bool {
            foreach ($initialLevels as $levelTypeKey => $initialLevel) {
                if ($initialLevel < $currentLevels[$levelTypeKey]) {
                    return false;
                }
            }
            return true;
        };

        /** @var int $minInitialLevel */
        $minInitialLevel = min($initialLevels);
        $maxDelta = 0;
        foreach ($initialLevels as $initialLevel) {
            $maxDelta = max($maxDelta, LogLevel::getHighest() - $initialLevel);
        }
        foreach (RangeUtil::generateDown(LogLevel::getHighest(), $minInitialLevel) as $baseLevel) {
            Assert::assertGreaterThan(LogLevel::OFF, $baseLevel);
            $currentLevels = [];
            foreach (self::LOG_LEVEL_FOR_CODE_KEYS as $levelTypeKey) {
                $currentLevels[$levelTypeKey] = $baseLevel;
            }
            yield $currentLevels;

            foreach (RangeUtil::generate(1, $maxDelta + 1) as $delta) {
                foreach (self::LOG_LEVEL_FOR_CODE_KEYS as $levelTypeKey) {
                    if ($baseLevel < $initialLevels[$levelTypeKey] + $delta) {
                        continue;
                    }
                    $currentLevels[$levelTypeKey] = $baseLevel - $delta;
                    if (!$haveCurrentLevelsReachedInitial($currentLevels)) {
                        yield $currentLevels;
                    }
                    $currentLevels[$levelTypeKey] = $baseLevel;
                }
            }
        }
    }

    /**
     * @param array<string, int> $initialLevels
     *
     * @return iterable<array<string, int>>
     */
    protected static function generateLevelsForRunAndEscalateLogLevelOnFailure(
        array $initialLevels,
        int $eachLevelsSetMaxCount
    ): iterable {
        $result = self::generateEscalatedLogLevels($initialLevels);
        $result = IterableUtilForTests::concat($result, [$initialLevels]);
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        $result = IterableUtilForTests::duplicateEachElement($result, $eachLevelsSetMaxCount);
        return $result;
    }

    /**
     * @param array<string, int> $logLevels
     *
     * @return array<string, string>
     */
    protected static function logLevelsWithNames(array $logLevels): array
    {
        $result = [];
        foreach ($logLevels as $levelTypeKey => $logLevel) {
            $result[$levelTypeKey] = LogLevel::intToName($logLevel) . ' (' . $logLevel . ')';
        }
        return $result;
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey, TValue> $array
     * @param TKey                $key
     *
     * @return TValue
     */
    public static function &assertAndGetFromArrayByKey(array $array, $key)
    {
        self::assertArrayHasKey($key, $array);
        return $array[$key];
    }

    /**
     * @param TransactionContext $ctx
     * @param string             $key
     * @param mixed              $value
     *
     * @return void
     */
    public static function setContextCustom(TransactionContext $ctx, string $key, $value): void
    {
        $valueSerialized = base64_encode(serialize($value));
        $nonKeywordStringMaxLength = self::getTracerFromAppCode()->getConfig()->nonKeywordStringMaxLength();
        self::assertLessThanOrEqual($nonKeywordStringMaxLength, strlen($valueSerialized));
        $ctx->setCustom($key, $valueSerialized);
        $ctx->setCustom($key . '_crc', crc32($valueSerialized));
    }

    /**
     * @param TransactionContextDto $ctx
     * @param string                $key
     *
     * @return mixed
     */
    public static function getContextCustom(TransactionContextDto $ctx, string $key)
    {
        self::assertNotNull($ctx->custom);
        $valueSerialized = self::assertAndGetFromArrayByKey($ctx->custom, $key);
        self::assertIsString($valueSerialized);
        self::assertArrayHasKey($key . '_crc', $ctx->custom);
        $receivedCrc = self::assertAndGetFromArrayByKey($ctx->custom, $key . '_crc');
        $calculatedCrc = crc32($valueSerialized);
        self::assertSame($receivedCrc, $calculatedCrc);
        return unserialize(base64_decode($valueSerialized));
    }

    protected static function disableTimingDependentFeatures(AppCodeHostParams $appCodeParams): void
    {
        // Disable Span Compression feature to have all the expected spans individually
        $appCodeParams->setAgentOption(OptionNames::SPAN_COMPRESSION_ENABLED, false);
        // Enable span stack trace collection for span with any duration
        $appCodeParams->setAgentOption(OptionNames::SPAN_STACK_TRACE_MIN_DURATION, 0);
    }

    protected static function setConfigIfNotNull(MixedMap $testArgs, string $optName, AppCodeHostParams $appCodeParams): void
    {
        $optVal = $testArgs->getNullableString($optName);
        if ($optVal !== null) {
            $appCodeParams->setAgentOption($optName, $optVal);
        }
    }
}
