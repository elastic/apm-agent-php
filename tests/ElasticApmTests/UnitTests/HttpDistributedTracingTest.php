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
use Elastic\Apm\Impl\Util\ArrayUtil;
use ElasticApmTests\ExternalTestData;
use ElasticApmTests\Util\TestCaseBase;

class HttpDistributedTracingTest extends TestCaseBase
{
    private static function buildDistributedTracingData(
        string $traceId,
        string $parentId,
        bool $isSampled
    ): DistributedTracingData {
        $data = new DistributedTracingData();
        $data->traceId = strtolower($traceId);
        $data->parentId = strtolower($parentId);
        $data->isSampled = $isSampled;
        return $data;
    }

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
        return [
            '00' . '-' . $traceId . '-' . $parentId . '-' . ($isSampled ? '01' : '00'),
            self::buildDistributedTracingData($traceId, $parentId, $isSampled)
        ];
    }

    /**
     * @return iterable<array<string|DistributedTracingData|null>>
     * @phpstan-return iterable<array{string, ?DistributedTracingData}>
     */
    public function dataProviderForTestBuildTraceParentHeader(): iterable
    {
        yield self::buildValidInput('0af7651916cd43dd8448eb211c80319c', 'b9c7c989f97918e1', true);
        yield self::buildValidInput('0af7651916cd43dd8448eb211c80319c', 'b9c7c989f97918e1', false);
        yield self::buildValidInput('11111111111111111111111111111111', '2222222222222222', true);
        yield self::buildValidInput('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA', 'bbbbbbbbbbbbbbbb', true);
    }

    /**
     * @dataProvider dataProviderForTestBuildTraceParentHeader
     *
     * @param DistributedTracingData $data
     * @param string                 $expectedHeaderValue
     */
    public function testBuildTraceParentHeader(string $expectedHeaderValue, DistributedTracingData $data): void
    {
        $builtHeaderValue = HttpDistributedTracing::buildTraceParentHeader($data);
        self::assertEquals(strtolower($expectedHeaderValue), $builtHeaderValue);
    }

    /**
     * @return iterable<array<string|DistributedTracingData|null>>
     * @phpstan-return iterable<array{string, ?DistributedTracingData}>
     */
    public function dataProviderForTestParseTraceParentHeader(): iterable
    {
        yield from $this->dataProviderForTestBuildTraceParentHeader();

        // Future version with currently correct structure
        yield [
            '01-0af7651916cd43dd8448eb211c80319c-b9c7c989f97918e1-01',
            self::buildDistributedTracingData('0af7651916cd43dd8448eb211c80319c', 'b9c7c989f97918e1', true)
        ];

        // Erroneous input:

        yield ['', null]; // empty string
        yield ['000af7651916cd43dd8448eb211c80319cb9c7c989f97918e101', null]; // delimiters are missing
        yield ['000af7651916cd43dd8448eb211c80319c-b9c7c989f97918e1-01', null]; // delimiters are missing
        yield ['00-0af7651916cd43dd8448eb211c80319cb9c7c989f97918e1-01', null]; // delimiters are missing
        yield ['00-0af7651916cd43dd8448eb211c80319c-b9c7c989f97918e101', null]; // delimiters are missing
        yield ['000af7651916cd43dd8448eb211c80319cb9c7c989f97918e1-01', null]; // delimiters are missing
        yield ['00-0af7651916cd43dd8448eb211c80319cb9c7c989f97918e101', null]; // delimiters are missing

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
        $isTraceParentValid = true;
        /** @var ?bool */
        $isTraceStateValid = null;
        $actualData = $httpDistributedTracing->parseHeadersImpl(
            [$headerValue] /* /* <- traceParentHeaders */,
            [] /* <- traceStateHeaders */,
            $isTraceParentValid /* <- ref */,
            $isTraceStateValid /* <- ref */
        );
        self::assertEquals($expectedData, $actualData);
        self::assertEquals($isTraceParentValid, $actualData !== null);
        self::assertNull($isTraceStateValid);
    }

    /**
     * @return iterable<array<DistributedTracingData|string>>
     * @phpstan-return iterable<array{DistributedTracingData, string}>
     */
    public function dataProviderForBuildTraceParentHeader(): iterable
    {
        foreach (self::dataProviderForTestParseTraceParentHeader() as $headerDataPair) {
            if ($headerDataPair[1] === null) {
                continue;
            }

            yield [$headerDataPair[1], strtolower($headerDataPair[0])];
        }
    }

    /**
     * @return iterable<array<mixed>>
     */
    public function dataProviderForW3cData(): iterable
    {
        $w3cDataJson = ExternalTestData::readJsonSpecsFile('w3c_distributed_tracing.json');
        self::assertIsArray($w3cDataJson);
        foreach ($w3cDataJson as $entry) {
            yield [$entry];
        }
    }

    /**
     * @dataProvider dataProviderForW3cData
     *
     * @param array<string, mixed> $entry
     */
    public function testOnW3cDataEntry(array $entry): void
    {
        self::assertIsArray($entry);
        $headers = $entry['headers'];
        self::assertIsArray($headers);
        $expectedIsTraceParentValid = $entry['is_traceparent_valid'];
        $expectedIsTraceStateValid = ArrayUtil::getValueIfKeyExistsElse('is_tracestate_valid', $entry, null);

        $traceParentHeaders = [];
        $traceStateHeaders = [];
        foreach ($headers as $header) {
            self::assertIsArray($header);
            self::assertCount(2, $header);
            if (strtolower($header[0]) === 'traceparent') {
                $traceParentHeaders[] = $header[1];
            }
            if (strtolower($header[0]) === 'tracestate') {
                $traceStateHeaders[] = $header[1];
            }
        }

        $httpDistributedTracing = new HttpDistributedTracing(self::noopLoggerFactory());

        $actualIsTraceParentValid = true;
        /** @var ?bool */
        $actualIsTraceStateValid = null;
        $distTracingData = $httpDistributedTracing->parseHeadersImpl(
            $traceParentHeaders,
            $traceStateHeaders,
            /* ref */ $actualIsTraceParentValid,
            /* ref */ $actualIsTraceStateValid
        );
        self::assertSame($actualIsTraceParentValid, $distTracingData !== null);
        self::assertSame($expectedIsTraceParentValid, $actualIsTraceParentValid);
        if ($expectedIsTraceStateValid !== null) {
            // TODO: Sergey Kleyman: Implement: HttpDistributedTracingTest::testOnW3cDataEntry
            // self::assertSame($expectedIsTraceStateValid, $actualIsTraceStateValid);
        }
    }
}
