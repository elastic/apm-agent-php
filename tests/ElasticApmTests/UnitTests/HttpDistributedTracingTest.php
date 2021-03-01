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

/** @noinspection SpellCheckingInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\DistributedTracingData;
use Elastic\Apm\Impl\HttpDistributedTracing;
use ElasticApmTests\Util\TestCaseBase;

class HttpDistributedTracingTest extends TestCaseBase
{
    /**
     * @param string $traceId
     * @param string $parentId
     * @param bool   $isSampled
     *
     * @return array<string|DistributedTracingData|null>
     * @phpstan-return array{string, ?DistributedTracingData}
     */
    private static function buildValidInput(string $traceId, string $parentId, bool $isSampled): array
    {
        $data = new DistributedTracingData();
        $data->traceId = strtolower($traceId);
        $data->parentId = strtolower($parentId);
        $data->isSampled = $isSampled;
        return ['00' . '-' . $traceId . '-' . $parentId . '-' . ($isSampled ? '01' : '00'), $data];
    }

    /**
     * @return iterable<array<string|DistributedTracingData|null>>
     * @phpstan-return iterable<array{string, ?DistributedTracingData}>
     */
    public function dataProviderForTestParseTraceParentHeader(): iterable
    {
        yield self::buildValidInput('0af7651916cd43dd8448eb211c80319c', 'b9c7c989f97918e1', true);
        yield self::buildValidInput('0af7651916cd43dd8448eb211c80319c', 'b9c7c989f97918e1', false);
        yield self::buildValidInput('11111111111111111111111111111111', '2222222222222222', true);
        yield self::buildValidInput('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA', 'BBBBBBBBBBBBBBBB', true);

        // Erroneous input:

        yield ['', null]; // empty string
        yield ['000af7651916cd43dd8448eb211c80319cb9c7c989f97918e101', null]; // delimiters are missing
        yield ['000af7651916cd43dd8448eb211c80319c-b9c7c989f97918e1-01', null]; // delimiters are missing
        yield ['00-0af7651916cd43dd8448eb211c80319cb9c7c989f97918e1-01', null]; // delimiters are missing
        yield ['00-0af7651916cd43dd8448eb211c80319c-b9c7c989f97918e101', null]; // delimiters are missing
        yield ['000af7651916cd43dd8448eb211c80319cb9c7c989f97918e1-01', null]; // delimiters are missing
        yield ['00-0af7651916cd43dd8448eb211c80319cb9c7c989f97918e101', null]; // delimiters are missing

        yield ['01-0af7651916cd43dd8448eb211c80319c-b9c7c989f97918e1-01', null]; // version: unsupported
        yield ['-0af7651916cd43dd8448eb211c80319c-b9c7c989f97918e1-01', null]; // version: missing
        yield ['0af7651916cd43dd8448eb211c80319c-b9c7c989f97918e1-01', null]; // version: missing
        yield ['0-0af7651916cd43dd8448eb211c80319c-b9c7c989f97918e1-01', null]; // version: too short
        yield ['000-0af7651916cd43dd8448eb211c80319c-b9c7c989f97918e1-01', null]; // version: too long
        yield ['0k-0af7651916cd43dd8448eb211c80319c-b9c7c989f97918e1-01', null]; // version: not a hex number
        yield ['k0-0af7651916cd43dd8448eb211c80319c-b9c7c989f97918e1-01', null]; // version: not a hex number

        yield ['00-00000000000000000000000000000000-b9c7c989f97918e1-01', null]; // traceId: invalid
        yield ['01-0af7651916cd43dd8448eb211c80319-b9c7c989f97918e1-01', null]; // traceId too short
        yield ['01-0af7651916cd43dd8448eb211c80319cc-b9c7c989f97918e1-01', null]; // traceId too long

        yield ['00-0af7651916cd43dd8448eb211c80319c-0000000000000000-01', null]; // parentId: invalid
    }

    /**
     * @dataProvider dataProviderForTestParseTraceParentHeader
     *
     * @param string                      $headerValue
     * @param DistributedTracingData|null $expectedData
     */
    public function testParseTraceParentHeader(string $headerValue, ?DistributedTracingData $expectedData): void
    {
        $httpDistributedTracing = new HttpDistributedTracing(self::noopLoggerFactory());
        $actualData = $httpDistributedTracing->parseTraceParentHeader($headerValue);
        self::assertEquals($expectedData, $actualData);
    }

    /**
     * @return iterable<array<DistributedTracingData|string>>
     * @phpstan-return iterable<array{DistributedTracingData, string}>
     */
    public function dataProviderForTestBuildTraceParentHeader(): iterable
    {
        foreach (self::dataProviderForTestParseTraceParentHeader() as $headerDataPair) {
            if (is_null($headerDataPair[1])) {
                continue;
            }

            yield [$headerDataPair[1], strtolower($headerDataPair[0])];
        }
    }

    /**
     * @dataProvider dataProviderForTestBuildTraceParentHeader
     *
     * @param DistributedTracingData $data
     * @param string                 $expectedHeaderValue
     */
    public function testBuildHeader(DistributedTracingData $data, string $expectedHeaderValue): void
    {
        $builtHeaderValue = HttpDistributedTracing::buildTraceParentHeader($data);
        self::assertEquals($expectedHeaderValue, $builtHeaderValue);
    }
}
