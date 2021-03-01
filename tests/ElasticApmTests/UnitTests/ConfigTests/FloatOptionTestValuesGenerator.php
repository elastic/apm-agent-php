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

namespace ElasticApmTests\UnitTests\ConfigTests;

use Ds\Set;
use Elastic\Apm\Impl\Config\FloatOptionParser;
use Elastic\Apm\Impl\Config\NumericOptionParser;
use Elastic\Apm\Impl\Util\NumericUtil;
use ElasticApmTests\Util\FloatLimits;
use ElasticApmTests\Util\RandomUtilForTests;
use IteratorIterator;

/**
 * @extends NumericOptionTestValuesGeneratorBase<float>
 */
class FloatOptionTestValuesGenerator extends NumericOptionTestValuesGeneratorBase
{
    /** @var FloatOptionParser */
    protected $optionParser;

    public function __construct(FloatOptionParser $optionParser)
    {
        $this->optionParser = $optionParser;
    }

    /**
     * @return FloatOptionParser
     */
    protected function optionParser(): NumericOptionParser
    {
        return $this->optionParser;
    }

    /**
     * @return float
     */
    protected static function maxValueSupportedByType(): float
    {
        return FloatLimits::MAX;
    }

    /**
     * @return float
     */
    protected static function minValueSupportedByType(): float
    {
        return FloatLimits::MIN;
    }

    /**
     * @return iterable<OptionTestValidValue<float>>
     */
    protected function manualInterestingValues(): iterable
    {
        /** @var OptionTestValidValue<int> $intManualInterestingValue */
        foreach (self::intManualInterestingValues() as $intManualInterestingValue) {
            yield new OptionTestValidValue(
                $intManualInterestingValue->rawValue,
                floatval($intManualInterestingValue->parsedValue)
            );
        }

        yield new OptionTestValidValue('0.0', 0.0);
        yield new OptionTestValidValue('-0.0', 0.0);
        yield new OptionTestValidValue('+0.0', 0.0);
        yield new OptionTestValidValue('0.0e0', 0.0);
        yield new OptionTestValidValue('-0.0E-0', 0.0);
        yield new OptionTestValidValue('+0.0e+0', 0.0);

        yield new OptionTestValidValue('1.0', 1.0);
        yield new OptionTestValidValue('+1.0', 1.0);
        yield new OptionTestValidValue('-1.0', -1.0);
        yield new OptionTestValidValue('1.0E0', 1.0);
        yield new OptionTestValidValue('+1.0e0', 1.0);
        yield new OptionTestValidValue('-1.0E0', -1.0);

        yield new OptionTestValidValue('01.5e1', 15.0);
        yield new OptionTestValidValue('+5.1E2', 510.0);
        yield new OptionTestValidValue('-2.5e-3', -0.0025);
    }

    /**
     * @return iterable<float>
     */
    protected function autoGeneratedInterestingValuesToDiff(): iterable
    {
        /** @var Set<float> */
        $result = new Set();

        /** @var int $intInterestingValue */
        foreach ($this->intInterestingValuesToDiff() as $intInterestingValue) {
            $result->add(floatval($intInterestingValue));
        }

        if (!is_null($this->optionParser()->minValidValue())) {
            $result->add(
                $this->optionParser()->minValidValue(),
                $this->optionParser()->minValidValue() / 2
            );
        }
        if (!is_null($this->optionParser()->maxValidValue())) {
            $result->add(
                $this->optionParser()->maxValidValue(),
                $this->optionParser()->maxValidValue() / 2
            );
        }
        $result->add(
            FloatLimits::MIN,
            FloatLimits::MIN / 2,
            FloatLimits::MAX / 2,
            FloatLimits::MAX
        );

        return new IteratorIterator($result);
    }

    /**
     * @return iterable<float>
     */
    protected function autoGeneratedInterestingValueDiffs(): iterable
    {
        foreach (self::intInterestingDiffs() as $intDiff) {
            foreach (self::fractionInterestingDiffs() as $fractionDiff) {
                yield $intDiff + $fractionDiff;
                if ($intDiff > $fractionDiff) {
                    yield $intDiff - $fractionDiff;
                }
            }
        }
    }

    /**
     * @return iterable<float>
     */
    protected static function fractionInterestingDiffs(): iterable
    {
        yield from [0.0, 0.001, 0.01, 0.1, 0.5, 0.9];
    }

    /**
     * @param float $min
     * @param float $max
     *
     * @return float
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    protected static function randomValue($min, $max)
    {
        return RandomUtilForTests::generateFloatInRange($min, $max);
    }

    public static function isInIntRange(float $value): bool
    {
        return NumericUtil::isInClosedInterval(PHP_INT_MIN, $value, PHP_INT_MAX);
    }

    /**
     * @param float $value
     *
     * @return OptionTestValidValue<float>
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    protected static function createOptionTestValidValue($value)
    {
        $valueAsString = strval($value);
        return new OptionTestValidValue($valueAsString, floatval($valueAsString));
    }

    /**
     * @return iterable<OptionTestValidValue<float>>
     */
    public function validValues(): iterable
    {
        /** @var OptionTestValidValue<float> $value */
        foreach (parent::validValues() as $value) {
            yield $value;

            /** @var float $roundedValue */
            foreach ([ceil($value->parsedValue), floor($value->parsedValue)] as $roundedValue) {
                if (self::isInIntRange($roundedValue)) {
                    yield static::createOptionTestValidValue($roundedValue);
                    $valueAsString = strval(intval($roundedValue));
                    yield new OptionTestValidValue($valueAsString, floatval($valueAsString));
                }
            }
        }
    }
}
