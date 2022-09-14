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
    private $shouldCombineOnlyWithDefaultValuesForOthers = [];

    /** @var bool[] */
    private $isOneDimension = [];

    /** @var array<array<array<string|int, mixed>>> */
    private $generatorsConvertedToArrays = [];

    private function __construct()
    {
    }

    public static function startNew(): self
    {
        return new self();
    }

    /**
     * @param iterable<mixed> $generator
     *
     * @return $this
     */
    public function addDimensionCombineOnlyWithDefaultValuesForOthers(iterable $generator): self
    {
        return $this->addDimension(/* shouldCombineOnlyWithDefaultValuesForOthers: */ true, $generator);
    }

    /**
     * @param iterable<mixed> $generator
     *
     * @return $this
     */
    public function addDimensionCombineWithAllValuesForOthers(iterable $generator): self
    {
        return $this->addDimension(/* shouldCombineOnlyWithDefaultValuesForOthers: */ false, $generator);
    }

    /**
     * @param bool            $shouldCombineOnlyWithDefaultValuesForOthers
     * @param iterable<mixed> $generator
     *
     * @return $this
     */
    public function addDimension(
        bool $shouldCombineOnlyWithDefaultValuesForOthers,
        iterable $generator
    ): self {
        $this->shouldCombineOnlyWithDefaultValuesForOthers[] = $shouldCombineOnlyWithDefaultValuesForOthers;
        $this->isOneDimension[] = true;
        $this->generatorsConvertedToArrays[] = IterableUtilForTests::toArray($generator);

        return $this;
    }

    /**
     * @param bool            $shouldCombineOnlyWithDefaultValuesForOthers
     * @param iterable<mixed> $generator
     *
     * @return $this
     */
    public function addMultipleDimensions(
        bool $shouldCombineOnlyWithDefaultValuesForOthers,
        iterable $generator
    ): self {
        $this->shouldCombineOnlyWithDefaultValuesForOthers[] = $shouldCombineOnlyWithDefaultValuesForOthers;
        $this->isOneDimension[] = false;
        $this->generatorsConvertedToArrays[] = IterableUtilForTests::toArray($generator);
        return $this;
    }

    /**
     * @return array<mixed>
     */
    private function buildDefaultGeneratedValues(): iterable
    {
        $generatedValues = [];
        $i = 0;
        foreach ($this->generatorsConvertedToArrays as $generatorConvertedToArray) {
            TestCase::assertTrue($this->isOneDimension[$i]);
            TestCase::assertNotCount(0, $generatorConvertedToArray);
            $generatedValues[] = $generatorConvertedToArray[0];
            ++$i;
        }
        return $generatedValues;
    }

    /**
     * @param array<mixed> $generatedValues
     *
     * @return array<string|int, mixed>
     */
    private static function convertGeneratedValues(array $generatedValues): array
    {
        return $generatedValues;
    }

    /**
     * @return iterable<array<string|int, mixed>>
     */
    public function build(): iterable
    {
        $generatedValues = $this->buildDefaultGeneratedValues();
        foreach ($this->shouldCombineOnlyWithDefaultValuesForOthers as $shouldCombineOnlyWithDefaultValuesForOthers) {
            TestCase::assertTrue($shouldCombineOnlyWithDefaultValuesForOthers);
        }
        return self::convertGeneratedValues($generatedValues);
    }
}
