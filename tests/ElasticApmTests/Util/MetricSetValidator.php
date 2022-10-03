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

use Elastic\Apm\Impl\MetricSet;
use Elastic\Apm\Impl\Util\ArrayUtil;
use PHPUnit\Framework\TestCase;

final class MetricSetValidator
{
    use AssertValidTrait;

    /** @var MetricSetExpectations */
    protected $expectations;

    /** @var MetricSet */
    protected $actual;

    private function __construct(MetricSetExpectations $expectations, MetricSet $actual)
    {
        $this->expectations = $expectations;
        $this->actual = $actual;
    }

    private function assertValidImpl(): void
    {
        self::assertValidTimestamp($this->actual->timestamp, $this->expectations);

        self::assertValidNullableKeywordString($this->actual->transactionName);
        self::assertValidNullableKeywordString($this->actual->transactionType);
        self::assertValidNullableKeywordString($this->actual->spanType);
        self::assertValidNullableKeywordString($this->actual->spanSubtype);

        TestCaseBase::assertSameNullness($this->actual->transactionName, $this->actual->transactionType);

        if ($this->actual->spanSubtype !== null) {
            TestCase::assertNotNull($this->actual->spanType);
        }
        if ($this->actual->spanType !== null) {
            TestCase::assertNotNull($this->actual->transactionType);
        }

        self::assertValidSamples($this->actual->samples);
    }

    public static function assertValid(MetricSet $actual, ?MetricSetExpectations $expectations = null): void
    {
        (new self($expectations ?? new MetricSetExpectations(), $actual))->assertValidImpl();
    }

    /**
     * @param mixed $samples
     *
     * @return array<string, array<string, float|int>>
     */
    public static function assertValidSamples($samples): array
    {
        TestCase::assertTrue(is_array($samples));
        /** @var array<mixed, mixed> $samples */
        TestCase::assertTrue(!ArrayUtil::isEmpty($samples));

        foreach ($samples as $key => $valueArr) {
            self::assertValidKeywordString($key);
            TestCase::assertTrue(is_array($valueArr));
            /** @var array<mixed, mixed> $valueArr */
            TestCase::assertTrue(count($valueArr) === 1);
            TestCase::assertTrue(array_key_exists('value', $valueArr));
            $value = $valueArr['value'];
            TestCase::assertTrue(is_int($value) || is_float($value));
            /** @var float|int $value */
        }
        /** @var array<string, array<string, float|int>> $samples */
        return $samples;
    }
}
