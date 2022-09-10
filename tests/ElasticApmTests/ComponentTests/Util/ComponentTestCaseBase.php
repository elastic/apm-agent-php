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
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use ElasticApmTests\Util\DataFromAgent;
use ElasticApmTests\Util\TestCaseBase;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ComponentTestCaseBase extends TestCaseBase
{
    /** @var ?TestCaseHandle */
    private $testCaseHandle = null;

    protected function getTestCaseHandle(): TestCaseHandle
    {
        ComponentTestsPhpUnitExtension::initSingletons();
        return $this->testCaseHandle ?? ($this->testCaseHandle = new TestCaseHandle());
    }

    /** @inheritDoc */
    public function tearDown(): void
    {
        if ($this->testCaseHandle !== null) {
            $this->testCaseHandle->tearDown();
        }
    }

    public static function appCodeEmpty(): void
    {
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
     * @param string                $optName
     * @param null|string|int|float $optVal
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

    /**
     * @param array<string, mixed> $appCodeArgs
     * @param string               $appArgNameKey
     *
     * @return mixed
     */
    protected static function getMandatoryAppCodeArg(array $appCodeArgs, string $appArgNameKey)
    {
        if (!array_key_exists($appArgNameKey, $appCodeArgs)) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    'Expected key is not found in app code args',
                    ['appArgNameKey' => $appArgNameKey, 'appCodeArgs' => $appCodeArgs]
                )
            );
        }
        return $appCodeArgs[$appArgNameKey];
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
        $this->verifyOneEmptyTransaction($dataFromAgent);
        return $dataFromAgent;
    }

    protected function verifyOneEmptyTransaction(DataFromAgent $dataFromAgent): TransactionData
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
        /** @var AutoInstrumentationBase $instr */
        $instr = new $instrClassName(self::buildTracerForTests()->build());
        $actualNames = $instr->otherNames();
        $actualNames[] = $instr->name();
        self::assertEqualLists($expectedNames, $actualNames);
        self::assertTrue($instr->isEnabled());

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
            foreach ($genDisabledVariants($name) as $disableInstrumentationsOptVal) {
                $tracer = self::buildTracerForTests()
                              ->withConfig(OptionNames::DISABLE_INSTRUMENTATIONS, $disableInstrumentationsOptVal)
                              ->build();
                $instr = new $instrClassName($tracer);
                self::assertFalse($instr->isEnabled(), $disableInstrumentationsOptVal);
            }
        }

        /**
         * @param string $name
         *
         * @return iterable<string>
         */
        $genEnabledVariants = function (): iterable {
            yield '*someOtherDummyInstrumentation*';
            yield '*someOtherDummyInstrumentationA*,  *someOtherDummyInstrumentationB*';
        };

        foreach ($genEnabledVariants() as $disableInstrumentationsOptVal) {
            $tracer = self::buildTracerForTests()
                          ->withConfig(OptionNames::DISABLE_INSTRUMENTATIONS, $disableInstrumentationsOptVal)
                          ->build();
            $instr = new $instrClassName($tracer);
            self::assertTrue($instr->isEnabled(), $disableInstrumentationsOptVal);
        }
    }
}
