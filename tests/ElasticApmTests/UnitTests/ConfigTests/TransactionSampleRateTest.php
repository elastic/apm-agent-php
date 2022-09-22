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

use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\HttpDistributedTracing;
use Elastic\Apm\Impl\Tracer;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;
use ElasticApmTests\Util\TracerBuilderForTests;

class TransactionSampleRateTest extends TracerUnitTestCaseBase
{
    /**
     * @return iterable<array{string, string}>
     */
    public function dataProviderForTransactionSampleRate(): iterable
    {
        yield ['0', '0'];
        yield ['0.0', '0'];
        yield ['0.00', '0'];
        yield ['0.01', '0.01'];
        yield ['0.1', '0.1'];
        yield ['0.123', '0.123'];
        yield ['0.25', '0.25'];
        yield ['0.3', '0.3'];
        yield ['0.333', '0.333'];
        yield ['0.3333', '0.3333'];
        yield ['0.5', '0.5'];
        yield ['0.50', '0.5'];
        yield ['1', '1'];
        yield ['1.0', '1'];
        yield ['invalid', '1'];

        /**
         * @link https://github.com/elastic/apm/blob/master/specs/agents/tracing-sampling.md
         */
        yield ['0.00001', '0.0001'];
        yield ['0.00002', '0.0001'];
        yield ['0.55554', '0.5555'];
        yield ['0.55555', '0.5556'];
        yield ['0.55556', '0.5556'];
        yield ['0.33333', '0.3333'];
        yield ['0.33335', '0.3334'];
        yield ['0.98765', '0.9877'];
    }

    /**
     * @dataProvider dataProviderForTransactionSampleRate
     *
     * @param string $configured
     * @param string $expectedAsString
     */
    public function testTransactionSampleRate(string $configured, string $expectedAsString): void
    {
        $this->setUpTestEnv(
            function (TracerBuilderForTests $builder) use ($configured): void {
                $builder->withConfig(OptionNames::TRANSACTION_SAMPLE_RATE, $configured);
            }
        );

        /** @var Tracer $tracer */
        $tracer = $this->tracer;
        self::assertInstanceOf(Tracer::class, $tracer);
        $actual = $tracer->getConfig()->transactionSampleRate();
        self::assertSame($expectedAsString, HttpDistributedTracing::convertSampleRateToString($actual));
        self::assertSame(floatval($expectedAsString), $actual);
    }
}
