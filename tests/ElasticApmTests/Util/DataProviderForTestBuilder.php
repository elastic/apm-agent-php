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

use PHPUnit\Framework\TestCase;

final class DataProviderForTestBuilder
{
    /** @var bool[] */
    private $onlyFirstValueCombinable = [];

    /** @var array<callable(array<mixed, mixed>): iterable<array<mixed, mixed>>> */
    private $generators = [];

    /** @var bool */
    private $shouldWrapResultIntoArray = false;

    private function assertValid(): void
    {
        TestCase::assertSameSize($this->generators, $this->onlyFirstValueCombinable);
    }

    /**
     * @param bool $onlyFirstValueCombinable
     * @param callable(array<mixed, mixed>): iterable<array<mixed, mixed>> $generator
     *
     * @return $this
     */
    public function addGenerator(bool $onlyFirstValueCombinable, callable $generator): self
    {
        $this->assertValid();

        $this->onlyFirstValueCombinable[] = $onlyFirstValueCombinable;
        TestCase::assertFalse(IterableUtilForTests::isEmpty($generator([])));
        $this->generators[] = $generator;

        $this->assertValid();
        return $this;
    }

    /**
     * @param callable(array<mixed, mixed> $resultSoFar): iterable<array<mixed, mixed>> $generator
     *
     * @return $this
     */
    public function addGeneratorOnlyFirstValueCombinable(callable $generator): self
    {
        return $this->addGenerator(/* onlyFirstValueCombinable: */ true, $generator);
    }

    /**
     * @param callable(array<mixed, mixed>): iterable<array<mixed, mixed>> $generator
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
     * @param bool                   $onlyFirstValueCombinable
     * @param iterable<mixed, mixed> $iterable
     *
     * @return $this
     */
    public function addDimension(bool $onlyFirstValueCombinable, iterable $iterable): self
    {
        $this->addGenerator(
            $onlyFirstValueCombinable,
            /**
             * @param array<mixed, mixed> $resultSoFar
             * @return iterable<array<mixed, mixed>>
             */
            function (array $resultSoFar) use ($iterable): iterable {
                $expectedKeyForList = 0;
                foreach ($iterable as $key => $val) {
                    yield array_merge($resultSoFar, ($key === $expectedKeyForList) ? [$val] : [$key => $val]);
                    ++$expectedKeyForList;
                }
            }
        );
        return $this;
    }

    /**
     * @param iterable<mixed, mixed> $iterable
     *
     * @return $this
     */
    public function addDimensionOnlyFirstValueCombinable(iterable $iterable): self
    {
        return $this->addDimension(/* onlyFirstValueCombinable: */ true, $iterable);
    }

    /**
     * @param iterable<mixed, mixed> $iterable
     *
     * @return $this
     */
    public function addDimensionAllValuesCombinable(iterable $iterable): self
    {
        return $this->addDimension(/* onlyFirstValueCombinable: */ false, $iterable);
    }

    /**
     * @param string                 $dimensionKey
     * @param bool                   $onlyFirstValueCombinable
     * @param iterable<mixed, mixed> $iterable
     *
     * @return $this
     */
    public function addKeyedDimension(string $dimensionKey, bool $onlyFirstValueCombinable, iterable $iterable): self
    {
        $this->addGenerator(
            $onlyFirstValueCombinable,
            /**
             * @param array<mixed, mixed> $resultSoFar
             * @return iterable<array<mixed, mixed>>
             */
            function (array $resultSoFar) use ($dimensionKey, $iterable): iterable {
                $expectedKeyForList = 0;
                foreach ($iterable as $key => $val) {
                    TestCase::assertSame($expectedKeyForList, $key);
                    yield array_merge($resultSoFar, [$dimensionKey => $val]);
                    ++$expectedKeyForList;
                }
            }
        );
        return $this;
    }

    /**
     * @param string                 $dimensionKey
     * @param iterable<mixed, mixed> $iterable
     *
     * @return $this
     */
    public function addKeyedDimensionOnlyFirstValueCombinable(string $dimensionKey, iterable $iterable): self
    {
        return $this->addKeyedDimension($dimensionKey, /* onlyFirstValueCombinable: */ true, $iterable);
    }

    /**
     * @param string                      $dimensionKey
     * @param iterable<mixed, mixed> $iterable
     *
     * @return $this
     *
     * @noinspection PhpUnused
     */
    public function addKeyedDimensionAllValuesCombinable(string $dimensionKey, iterable $iterable): self
    {
        return $this->addKeyedDimension($dimensionKey, /* onlyFirstValueCombinable: */ false, $iterable);
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
        TestCase::assertTrue(IterableUtilForTests::getFirstValue($iterable, /* out */ $value));
        return $value;
    }

    /**
     * @param int                 $genIndexForAllValues
     * @param array<mixed, mixed> $resultSoFar
     * @param int                 $currentGenIndex
     *
     * @return iterable<array<mixed, mixed>>
     */
    private function buildImpl(int $genIndexForAllValues, array $resultSoFar, int $currentGenIndex): iterable
    {
        TestCase::assertLessThanOrEqual(count($this->generators), $currentGenIndex);
        if ($currentGenIndex === count($this->generators)) {
            yield $this->shouldWrapResultIntoArray ? [$resultSoFar] : $resultSoFar;
            return;
        }

        $iterable = $this->generators[$currentGenIndex]($resultSoFar);
        $shouldGenAfterFirst
            = ($currentGenIndex === $genIndexForAllValues) || (!$this->onlyFirstValueCombinable[$currentGenIndex]);
        $resultsToGen = $shouldGenAfterFirst ? $iterable : [self::getIterableFirstValue($iterable)];
        $shouldGenFirst = ($genIndexForAllValues === 0) || ($currentGenIndex !== $genIndexForAllValues);
        $resultsToGen = $shouldGenFirst ? $resultsToGen : IterableUtilForTests::skipFirst($resultsToGen);

        foreach ($resultsToGen as $resultSoFarPlusCurrent) {
            /** @var array<mixed, mixed> $resultSoFarPlusCurrent */
            yield from $this->buildImpl($genIndexForAllValues, $resultSoFarPlusCurrent, $currentGenIndex + 1);
        }
    }

    /**
     * @param array<mixed, iterable<mixed>> $iterables
     *
     * @return callable(array<mixed, mixed> $resultSoFar): iterable<array<mixed, mixed>> $generator
     */
    public static function cartesianProductGenerator(array $iterables): callable
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
     * @param array<mixed, iterable<mixed>> $iterables
     *
     * @return $this
     */
    public function addCartesianProduct(bool $onlyFirstValueCombinable, array $iterables): self
    {
        return $this->addGenerator($onlyFirstValueCombinable, self::cartesianProductGenerator($iterables));
    }

    /**
     * @param array<mixed, iterable<mixed>> $iterables
     *
     * @return $this
     */
    public function addCartesianProductOnlyFirstValueCombinable(array $iterables): self
    {
        return $this->addCartesianProduct(/* onlyFirstValueCombinable: */ true, $iterables);
    }

    /**
     * @param array<mixed, iterable<mixed>> $iterables
     *
     * @return $this
     *
     * @noinspection PhpUnused
     */
    public function addCartesianProductAllValuesCombinable(array $iterables): self
    {
        return $this->addCartesianProduct(/* onlyFirstValueCombinable: */ false, $iterables);
    }

    public function wrapResultIntoArray(): self
    {
        $this->assertValid();
        $this->shouldWrapResultIntoArray = true;
        return $this;
    }

    /**
     * @return iterable<array<mixed, mixed>>
     */
    public function build(): iterable
    {
        $this->assertValid();
        TestCase::assertNotCount(0, $this->generators);

        for ($genIndexForAllValues = 0; $genIndexForAllValues < count($this->generators); ++$genIndexForAllValues) {
            if ($genIndexForAllValues !== 0 && !$this->onlyFirstValueCombinable[$genIndexForAllValues]) {
                continue;
            }
            yield from $this->buildImpl($genIndexForAllValues, /* resultSoFar: */ [], 0 /* currentGenIndex */);
        }
    }
}
