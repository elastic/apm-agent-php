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

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\NoopLoggerFactory;
use Elastic\Apm\Impl\Util\RangeUtil;
use ElasticApmTests\ComponentTests\Util\ConfigUtilForTests;

final class DataProviderForTestBuilder
{
    /** @var bool[] */
    private $onlyFirstValueCombinable = [];

    /** @var array<callable(array<mixed>): iterable<array<mixed>>> */
    private $generators = [];

    /** @var ?int */
    private $emitOnlyDataSetWithIndex = null;

    private function assertValid(): void
    {
        TestCaseBase::assertSameSize($this->generators, $this->onlyFirstValueCombinable);
    }

    public static function isLongRunMode(): bool
    {
        $configForTests = ConfigUtilForTests::read(/* additionalConfigSource */ null, NoopLoggerFactory::singletonInstance());
        return $configForTests->isLongRunMode;
    }

    /**
     * @template T
     *
     * @param array<T>|callable(): iterable<T> $values
     *
     * @return callable(): iterable<T>
     */
    private static function adaptArrayToMultiUseIterable($values): callable
    {
        if (!is_array($values)) {
            return $values;
        }

        /**
         * @return iterable<T>
         */
        return function () use ($values): iterable {
            return $values;
        };
    }

    /**
     * @param bool $onlyFirstValueCombinable
     * @param callable(array<mixed>): iterable<array<mixed>> $generator
     *
     * @return $this
     */
    public function addGenerator(bool $onlyFirstValueCombinable, callable $generator): self
    {
        $this->assertValid();

        $this->onlyFirstValueCombinable[] = $onlyFirstValueCombinable;
        $this->generators[] = $generator;

        $this->assertValid();
        return $this;
    }

    /**
     * @param callable(array<mixed> $resultSoFar): iterable<array<mixed>> $generator
     *
     * @return $this
     */
    public function addGeneratorOnlyFirstValueCombinable(callable $generator): self
    {
        return $this->addGenerator(/* onlyFirstValueCombinable: */ true, $generator);
    }

    /**
     * @param callable(array<mixed>): iterable<array<mixed>> $generator
     *
     * @return $this
     *
     * @noinspection PhpUnused
     */
    public function addGeneratorAllValuesCombinable(callable $generator): self
    {
        return $this->addGenerator(/* onlyFirstValueCombinable: */ false, $generator);
    }

    /**
     * @param bool                                     $onlyFirstValueCombinable
     * @param array<mixed>|callable(): iterable<mixed> $values
     *
     * @return $this
     */
    public function addDimension(bool $onlyFirstValueCombinable, $values): self
    {
        $this->addGenerator(
            $onlyFirstValueCombinable,
            /**
             * @param array<mixed> $resultSoFar
             * @return iterable<array<mixed>>
             */
            function (array $resultSoFar) use ($values): iterable {
                $expectedKeyForList = 0;
                foreach (self::adaptArrayToMultiUseIterable($values)() as $key => $val) {
                    yield array_merge($resultSoFar, ($key === $expectedKeyForList) ? [$val] : [$key => $val]);
                    ++$expectedKeyForList;
                }
            }
        );
        return $this;
    }

    /**
     * @param array<mixed>|callable(): iterable<mixed> $values
     *
     * @return $this
     */
    public function addDimensionOnlyFirstValueCombinable($values): self
    {
        return $this->addDimension(/* onlyFirstValueCombinable: */ true, $values);
    }

    /**
     * @param array<mixed>|callable(): iterable<mixed> $values
     *
     * @return $this
     */
    public function addDimensionAllValuesCombinable($values): self
    {
        return $this->addDimension(/* onlyFirstValueCombinable: */ false, $values);
    }

    /**
     * @param string                                   $dimensionKey
     * @param bool                                     $onlyFirstValueCombinable
     * @param array<mixed>|callable(): iterable<mixed> $values
     *
     * @return $this
     */
    public function addKeyedDimension(string $dimensionKey, bool $onlyFirstValueCombinable, $values): self
    {
        $this->addGenerator(
            $onlyFirstValueCombinable,
            /**
             * @param array<mixed> $resultSoFar
             * @return iterable<array<mixed>>
             */
            function (array $resultSoFar) use ($dimensionKey, $values): iterable {
                $expectedKeyForList = 0;
                foreach (self::adaptArrayToMultiUseIterable($values)() as $key => $val) {
                    TestCaseBase::assertSame($expectedKeyForList, $key);
                    yield array_merge($resultSoFar, [$dimensionKey => $val]);
                    ++$expectedKeyForList;
                }
            }
        );
        return $this;
    }

    /**
     * @param string                                   $dimensionKey
     * @param array<mixed>|callable(): iterable<mixed> $values
     *
     * @return $this
     */
    public function addKeyedDimensionOnlyFirstValueCombinable(string $dimensionKey, $values): self
    {
        return $this->addKeyedDimension($dimensionKey, /* onlyFirstValueCombinable: */ true, $values);
    }

    /**
     * @param string                                   $dimensionKey
     * @param array<mixed>|callable(): iterable<mixed> $values
     *
     * @return $this
     *
     * @noinspection PhpUnused
     */
    public function addKeyedDimensionAllValuesCombinable(string $dimensionKey, $values): self
    {
        return $this->addKeyedDimension($dimensionKey, /* onlyFirstValueCombinable: */ false, $values);
    }

    /**
     * @param bool $onlyFirstValueCombinable
     *
     * @return $this
     */
    public function addBoolDimension(bool $onlyFirstValueCombinable): self
    {
        return $this->addDimension($onlyFirstValueCombinable, IterableUtilForTests::ALL_BOOL_VALUES);
    }

    /** @noinspection PhpUnused */
    public function addBoolDimensionOnlyFirstValueCombinable(): self
    {
        return $this->addBoolDimension(/* onlyFirstValueCombinable: */ true);
    }

    /** @noinspection PhpUnused */
    public function addBoolDimensionAllValuesCombinable(): self
    {
        return $this->addBoolDimension(/* onlyFirstValueCombinable: */ false);
    }

    /**
     * @param string $dimensionKey
     * @param bool   $onlyFirstValueCombinable
     *
     * @return $this
     */
    public function addBoolKeyedDimension(string $dimensionKey, bool $onlyFirstValueCombinable): self
    {
        $this->addKeyedDimension($dimensionKey, $onlyFirstValueCombinable, IterableUtilForTests::ALL_BOOL_VALUES);
        return $this;
    }

    /**
     * @param string $dimensionKey
     *
     * @return $this
     */
    public function addBoolKeyedDimensionOnlyFirstValueCombinable(string $dimensionKey): self
    {
        return $this->addBoolKeyedDimension($dimensionKey, /* onlyFirstValueCombinable: */ true);
    }

    /**
     * @param string $dimensionKey
     *
     * @return $this
     */
    public function addBoolKeyedDimensionAllValuesCombinable(string $dimensionKey): self
    {
        return $this->addBoolKeyedDimension($dimensionKey, /* onlyFirstValueCombinable: */ false);
    }

    /**
     * @param string $dimensionKey
     * @param mixed $value
     *
     * @return $this
     */
    public function addSingleValueKeyedDimension(string $dimensionKey, $value): self
    {
        return $this->addKeyedDimension($dimensionKey, /* onlyFirstValueCombinable: */ true, [$value]);
    }

    /**
     * @param iterable<mixed> $iterable
     *
     * @return mixed
     */
    private static function getIterableFirstValue(iterable $iterable)
    {
        TestCaseBase::assertTrue(IterableUtilForTests::getFirstValue($iterable, /* out */ $value));
        return $value;
    }

    /**
     * @param int          $genIndexForAllValues
     * @param array<mixed> $resultSoFar
     * @param int          $currentGenIndex
     *
     * @return iterable<array<mixed>>
     */
    private function buildForGenIndex(int $genIndexForAllValues, array $resultSoFar, int $currentGenIndex): iterable
    {
        TestCaseBase::assertLessThanOrEqual(count($this->generators), $currentGenIndex);
        if ($currentGenIndex === count($this->generators)) {
            yield $resultSoFar;
            return;
        }

        $currentGen = $this->generators[$currentGenIndex];
        TestCaseBase::assertFalse(IterableUtilForTests::isEmpty($currentGen($resultSoFar)));
        $iterable = $currentGen($resultSoFar);
        $shouldGenAfterFirst = ($currentGenIndex === $genIndexForAllValues) || (!$this->onlyFirstValueCombinable[$currentGenIndex]);
        $resultsToGen = $shouldGenAfterFirst ? $iterable : [self::getIterableFirstValue($iterable)];
        $shouldGenFirst = ($genIndexForAllValues === 0) || ($currentGenIndex !== $genIndexForAllValues);
        $resultsToGen = $shouldGenFirst ? $resultsToGen : IterableUtilForTests::skipFirst($resultsToGen);

        foreach ($resultsToGen as $resultSoFarPlusCurrent) {
            /** @var array<mixed> $resultSoFarPlusCurrent */
            yield from $this->buildForGenIndex($genIndexForAllValues, $resultSoFarPlusCurrent, $currentGenIndex + 1);
        }
    }

    /**
     * @param array<mixed, array<mixed>> $iterables
     *
     * @return callable(array<mixed> $resultSoFar): iterable<array<mixed>> $generator
     */
    private static function cartesianProduct(array $iterables): callable
    {
        /**
         * @param array<string|int, mixed> $resultSoFar
         *
         * @return iterable<array<string|int, mixed>>
         */
        return function (array $resultSoFar) use ($iterables): iterable {
            $cartesianProduct = CombinatorialUtilForTests::cartesianProduct($iterables);
            foreach ($cartesianProduct as $cartesianProductRow) {
                yield array_merge($resultSoFar, $cartesianProductRow);
            }
        };
    }

    /**
     * @param array<mixed, array<mixed>> $iterables
     *
     * @return $this
     */
    public function addCartesianProduct(bool $onlyFirstValueCombinable, array $iterables): self
    {
        return $this->addGenerator($onlyFirstValueCombinable, self::cartesianProduct($iterables));
    }

    /**
     * @param array<mixed, array<mixed>> $iterables
     *
     * @return $this
     */
    public function addCartesianProductOnlyFirstValueCombinable(array $iterables): self
    {
        return $this->addCartesianProduct(/* onlyFirstValueCombinable: */ true, $iterables);
    }

    /**
     * @param array<mixed, array<mixed>> $iterables
     *
     * @return $this
     *
     * @noinspection PhpUnused
     */
    public function addCartesianProductAllValuesCombinable(array $iterables): self
    {
        return $this->addCartesianProduct(/* onlyFirstValueCombinable: */ false, $iterables);
    }

    /**
     * @param string                                                 $masterSwitchKey
     * @param array<array<mixed>>|callable(): iterable<array<mixed>> $combinationsForEnabled
     * @param array<array<mixed>>|callable(): iterable<array<mixed>> $combinationsForDisabled
     *
     * @return callable(array<mixed>): iterable<array<mixed>>
     */
    public static function masterSwitchCombinationsGenerator(string $masterSwitchKey, $combinationsForEnabled, $combinationsForDisabled): callable
    {
        /**
         * @param array<mixed> $resultSoFar
         *
         * @return iterable<array<mixed>>
         */
        return function (array $resultSoFar) use ($masterSwitchKey, $combinationsForEnabled, $combinationsForDisabled): iterable {
            foreach (self::adaptArrayToMultiUseIterable($combinationsForEnabled)() as $combination) {
                yield array_merge([$masterSwitchKey => true], array_merge($combination, $resultSoFar));
            }
            foreach (self::adaptArrayToMultiUseIterable($combinationsForDisabled)() as $combination) {
                yield array_merge([$masterSwitchKey => false], array_merge($combination, $resultSoFar));
            }
        };
    }

    /**
     * @param string                 $dimensionKey
     * @param bool                   $onlyFirstValueCombinable
     * @param string                 $prevDimensionKey
     * @param mixed                  $prevDimensionTrueValue
     * @param iterable<mixed, mixed> $iterableForTrue
     * @param iterable<mixed, mixed> $iterableForFalse
     *
     * @return $this
     */
    public function addConditionalKeyedDimension(
        string $dimensionKey,
        bool $onlyFirstValueCombinable,
        string $prevDimensionKey,
        $prevDimensionTrueValue,
        iterable $iterableForTrue,
        iterable $iterableForFalse
    ): self {
        return $this->addGenerator(
            $onlyFirstValueCombinable,
            /**
             * @param array<mixed> $resultSoFar
             *
             * @return iterable<array<mixed>>
             */
            function (array $resultSoFar) use ($dimensionKey, $prevDimensionKey, $prevDimensionTrueValue, $iterableForTrue, $iterableForFalse): iterable {
                $iterable = $resultSoFar[$prevDimensionKey] === $prevDimensionTrueValue ? $iterableForTrue : $iterableForFalse;
                foreach ($iterable as $value) {
                    yield array_merge([$dimensionKey => $value], $resultSoFar);
                }
            }
        );
    }

    /**
     * @param string                 $dimensionKey
     * @param string                 $prevDimensionKey
     * @param mixed                  $prevDimensionTrueValue
     * @param iterable<mixed, mixed> $iterableForTrue
     * @param iterable<mixed, mixed> $iterableForFalse
     *
     * @return $this
     *
     * @noinspection PhpUnused
     */
    public function addConditionalKeyedDimensionOnlyFirstValueCombinable(
        string $dimensionKey,
        string $prevDimensionKey,
        $prevDimensionTrueValue,
        iterable $iterableForTrue,
        iterable $iterableForFalse
    ): self {
        return $this->addConditionalKeyedDimension($dimensionKey, /* onlyFirstValueCombinable: */ true, $prevDimensionKey, $prevDimensionTrueValue, $iterableForTrue, $iterableForFalse);
    }

    /**
     * @param string                 $dimensionKey
     * @param string                 $prevDimensionKey
     * @param mixed                  $prevDimensionTrueValue
     * @param iterable<mixed, mixed> $iterableForTrue
     * @param iterable<mixed, mixed> $iterableForFalse
     *
     * @return $this
     */
    public function addConditionalKeyedDimensionAllValueCombinable(
        string $dimensionKey,
        string $prevDimensionKey,
        $prevDimensionTrueValue,
        iterable $iterableForTrue,
        iterable $iterableForFalse
    ): self {
        return $this->addConditionalKeyedDimension($dimensionKey, /* onlyFirstValueCombinable: */ false, $prevDimensionKey, $prevDimensionTrueValue, $iterableForTrue, $iterableForFalse);
    }

    /**
     * @return iterable<array<mixed>>
     */
    public function buildWithoutDataSetName(): iterable
    {
        $this->assertValid();
        TestCaseBase::assertNotEmpty($this->generators);

        $dataSetIndex = 0;
        for ($genIndexForAllValues = 0; $genIndexForAllValues < count($this->generators); ++$genIndexForAllValues) {
            if ($genIndexForAllValues !== 0 && !$this->onlyFirstValueCombinable[$genIndexForAllValues]) {
                continue;
            }
            yield from $this->buildForGenIndex($genIndexForAllValues, /* resultSoFar: */ [], /* currentGenIndex */ 0);
        }
    }

    /**
     * @param iterable<array<mixed>> $dataSets
     *
     * @return iterable<string, array<mixed>>
     */
    public static function keyEachDataSetWithDbgDesc(iterable $dataSets, int $dataSetsCount, ?int $emitOnlyDataSetWithIndex = null): iterable
    {
        $dataSetIndex = 0;
        foreach ($dataSets as $dataSet) {
            ++$dataSetIndex;
            if ($emitOnlyDataSetWithIndex !== null && $dataSetIndex !== $emitOnlyDataSetWithIndex) {
                continue;
            }
            yield ('#' . $dataSetIndex . ' out of ' . $dataSetsCount . ': ' . LoggableToString::convert($dataSet)) => $dataSet;
        }
    }

    /**
     * @param int $emitOnlyDataSetWithIndex
     *
     * @return $this
     *
     * @noinspection PhpUnused
     */
    public function emitOnlyDataSetWithIndex(int $emitOnlyDataSetWithIndex): self
    {
        $this->emitOnlyDataSetWithIndex = $emitOnlyDataSetWithIndex;
        return $this;
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public function build(?int $emitOnlyDataSetWithIndex = null): iterable
    {
        return self::keyEachDataSetWithDbgDesc($this->buildWithoutDataSetName(), IterableUtilForTests::count($this->buildWithoutDataSetName()), $this->emitOnlyDataSetWithIndex);
    }

    /**
     * @return callable(): iterable<string, array<mixed>>
     */
    public function buildAsMultiUse(): callable
    {
        /**
         * @return iterable<string, array<mixed>>
         */
        return function (): iterable {
            return $this->build();
        };
    }

    /**
     * @param iterable<string, array<mixed>> $dataSets
     *
     * @return iterable<string, array{MixedMap}>
     */
    public static function convertEachDataSetToMixedMap(iterable $dataSets): iterable
    {
        foreach ($dataSets as $dbgDataSetName => $dataSet) {
            yield $dbgDataSetName => [new MixedMap(MixedMap::assertValidMixedMapArray($dataSet))];
        }
    }

    /**
     * @param int $count
     *
     * @return callable(): iterable<int>
     */
    public static function rangeUpTo(int $count): callable
    {
        /**
         * @return iterable<array<string|int, mixed>>
         */
        return function () use ($count): iterable {
            return RangeUtil::generateUpTo($count);
        };
    }
}
