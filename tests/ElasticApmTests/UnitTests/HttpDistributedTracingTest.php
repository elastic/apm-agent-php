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

use Elastic\Apm\Impl\DistributedTracingDataInternal;
use Elastic\Apm\Impl\HttpDistributedTracing;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\RangeUtil;
use ElasticApmTests\ExternalTestData;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;
use ElasticApmTests\Util\CharSetForTests;
use PHPUnit\Framework\TestCase;

class HttpDistributedTracingTest extends TracerUnitTestCaseBase
{
    /** @var ?CharSetForTests */
    private static $validVendorIdSuffixChars = null;

    private static function validVendorIdSuffixChars(): CharSetForTests
    {
        /*
         * key = simple-key / multi-tenant-key
         * simple-key = lcalpha 0*255( lcalpha / DIGIT / "_" / "-"/ "*" / "/" )
         * multi-tenant-key = tenant-id "@" system-id
         * tenant-id = ( lcalpha / DIGIT ) 0*240( lcalpha / DIGIT / "_" / "-"/ "*" / "/" )
         * system-id = lcalpha 0*13( lcalpha / DIGIT / "_" / "-"/ "*" / "/" )
         * lcalpha    = %x61-7A ; a-z
         *
         * @link https://www.w3.org/TR/trace-context/#key
         */
        if (self::$validVendorIdSuffixChars === null) {
            // ( lcalpha / DIGIT / "_" / "-"/ "*" / "/" )
            self::$validVendorIdSuffixChars = new CharSetForTests();
            self::$validVendorIdSuffixChars->addCharSet(CharSetForTests::lowerCaseLetters());
            self::$validVendorIdSuffixChars->addChar('_');
            self::$validVendorIdSuffixChars->addChar('-');
            self::$validVendorIdSuffixChars->addChar('*');
            self::$validVendorIdSuffixChars->addChar('/');
        }
        return self::$validVendorIdSuffixChars;
    }

    private static function generateVendorKeyEx(int $length, CharSetForTests $firstCharSet): string
    {
        TestCase::assertGreaterThanOrEqual(0, $length);
        if ($length === 0) {
            return '';
        }
        return $firstCharSet->getRandom() . self::validVendorIdSuffixChars()->generateString($length - 1);
    }

    private static function generateSimpleVendorKey(int $length): string
    {
        return self::generateVendorKeyEx($length, /* firstCharSet: */ CharSetForTests::lowerCaseLetters());
    }

    private static function generateMultiTenantVendorKey(int $tenantIdLength, int $systemIdLength): string
    {
        return self::generateVendorKeyEx($tenantIdLength, CharSetForTests::lowerCaseLettersAndDigits())
               . '@'
               . self::generateVendorKeyEx($systemIdLength, CharSetForTests::lowerCaseLetters());
    }

    private static function buildDistributedTracingData(
        string $traceId,
        string $parentId,
        bool $isSampled
    ): DistributedTracingDataInternal {
        $data = new DistributedTracingDataInternal();
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
     * @return array{string, ?DistributedTracingDataInternal}
     */
    private static function buildValidInput(string $traceId, string $parentId, bool $isSampled): array
    {
        return [
            '00' . '-' . $traceId . '-' . $parentId . '-' . ($isSampled ? '01' : '00'),
            self::buildDistributedTracingData($traceId, $parentId, $isSampled)
        ];
    }

    /**
     * @return iterable<array{string, ?DistributedTracingDataInternal}>
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
     * @param DistributedTracingDataInternal $data
     * @param string                         $expectedHeaderValue
     */
    public function testBuildTraceParentHeader(string $expectedHeaderValue, DistributedTracingDataInternal $data): void
    {
        $builtHeaderValue = HttpDistributedTracing::buildTraceParentHeader($data);
        self::assertEquals(strtolower($expectedHeaderValue), $builtHeaderValue);
    }

    /**
     * @return iterable<array{string, ?DistributedTracingDataInternal}>
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
     * @param string                          $headerValue
     * @param ?DistributedTracingDataInternal $expectedData
     */
    public function testParseTraceParentHeader(string $headerValue, ?DistributedTracingDataInternal $expectedData): void
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
     * @return iterable<array{DistributedTracingDataInternal, string}>
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

        $traceParentHeaderValues = [];
        $traceStateHeaderValues = [];
        foreach ($headers as $header) {
            self::assertIsArray($header);
            self::assertCount(2, $header);
            if (strtolower($header[0]) === 'traceparent') {
                $traceParentHeaderValues[] = $header[1];
            }
            if (strtolower($header[0]) === 'tracestate') {
                $traceStateHeaderValues[] = $header[1];
            }
        }

        $httpDistributedTracing = new HttpDistributedTracing(self::noopLoggerFactory());

        $actualIsTraceParentValid = true;
        /** @var ?bool */
        $actualIsTraceStateValid = null;
        $distTracingData = $httpDistributedTracing->parseHeadersImpl(
            $traceParentHeaderValues,
            $traceStateHeaderValues,
            /* ref */ $actualIsTraceParentValid,
            /* ref */ $actualIsTraceStateValid
        );
        $dbgMsg = LoggableToString::convert(
            [
                'entry'                      => $entry,
                'expectedIsTraceParentValid' => $expectedIsTraceParentValid,
                'actualIsTraceParentValid'   => $actualIsTraceParentValid,
                'expectedIsTraceStateValid'  => $expectedIsTraceStateValid,
                'actualIsTraceStateValid'    => $actualIsTraceStateValid,
                'traceParentHeaderValues'    => $traceParentHeaderValues,
                'traceStateHeaderValues'     => $traceStateHeaderValues,
                'distTracingData'            => $distTracingData,
            ],
            true /* <- prettyPrint */
        );

        self::assertSame($actualIsTraceParentValid, $distTracingData !== null, $dbgMsg);
        self::assertSame($expectedIsTraceParentValid, $actualIsTraceParentValid, $dbgMsg);
        if ($expectedIsTraceStateValid !== null) {
            self::assertSame($expectedIsTraceStateValid, $actualIsTraceStateValid, $dbgMsg);
        }
    }

    /**
     * @return iterable<array{string, bool}>
     */
    public function dataProviderForTraceStateVendorKey(): iterable
    {
        /*
         * simple-key = lcalpha 0*255( lcalpha / DIGIT / "_" / "-"/ "*" / "/" )
         * tenant-id = ( lcalpha / DIGIT ) 0*240( lcalpha / DIGIT / "_" / "-"/ "*" / "/" )
         * system-id = lcalpha 0*13( lcalpha / DIGIT / "_" / "-"/ "*" / "/" )
         *
         * @link https://www.w3.org/TR/trace-context/#key
         */

        yield ['a', true];
        yield ['1', true];
        yield ['a/', true];
        yield ['a$', false];
        yield ['^a', false];
        yield ['[a]', false];
        yield ['a\\', false];
        yield ['a@1', true];
        yield ['1@a', true];
        yield ['a@@1', false];

        yield [self::generateSimpleVendorKey(HttpDistributedTracing::TRACE_STATE_MAX_VENDOR_KEY_LENGTH - 1), true];
        yield [self::generateSimpleVendorKey(HttpDistributedTracing::TRACE_STATE_MAX_VENDOR_KEY_LENGTH), true];
        yield [self::generateSimpleVendorKey(HttpDistributedTracing::TRACE_STATE_MAX_VENDOR_KEY_LENGTH + 1), false];

        yield [
            self::generateMultiTenantVendorKey(
                HttpDistributedTracing::TRACE_STATE_MAX_TENANT_ID_LENGTH,
                HttpDistributedTracing::TRACE_STATE_MAX_SYSTEM_ID_LENGTH
            ),
            true
        ];
        yield [
            self::generateMultiTenantVendorKey(
                HttpDistributedTracing::TRACE_STATE_MAX_TENANT_ID_LENGTH + 1,
                HttpDistributedTracing::TRACE_STATE_MAX_SYSTEM_ID_LENGTH
            ),
            false
        ];
        yield [
            self::generateMultiTenantVendorKey(
                HttpDistributedTracing::TRACE_STATE_MAX_TENANT_ID_LENGTH + 1,
                HttpDistributedTracing::TRACE_STATE_MAX_SYSTEM_ID_LENGTH - 1
            ),
            false
        ];
        yield [
            self::generateMultiTenantVendorKey(
                HttpDistributedTracing::TRACE_STATE_MAX_TENANT_ID_LENGTH,
                HttpDistributedTracing::TRACE_STATE_MAX_SYSTEM_ID_LENGTH + 1
            ),
            false
        ];
        yield [
            self::generateMultiTenantVendorKey(
                HttpDistributedTracing::TRACE_STATE_MAX_TENANT_ID_LENGTH - 1,
                HttpDistributedTracing::TRACE_STATE_MAX_SYSTEM_ID_LENGTH + 1
            ),
            false
        ];
        yield [
            self::generateMultiTenantVendorKey(
                HttpDistributedTracing::TRACE_STATE_MAX_TENANT_ID_LENGTH + 1,
                HttpDistributedTracing::TRACE_STATE_MAX_SYSTEM_ID_LENGTH + 1
            ),
            false
        ];
    }

    /**
     * @dataProvider dataProviderForTraceStateVendorKey
     *
     * @param string $vendorKey
     * @param bool   $expectedIsValid
     */
    public function testTraceStateVendorKey(string $vendorKey, bool $expectedIsValid): void
    {
        $dbgMsg = LoggableToString::convert(['vendorKey' => $vendorKey, 'expectedIsValid' => $expectedIsValid]);
        $actualIsTraceParentValid = true;
        /** @var ?bool */
        $actualIsTraceStateValid = null;
        $httpDistributedTracing = new HttpDistributedTracing(self::noopLoggerFactory());
        $distTracingData = $httpDistributedTracing->parseHeadersImpl(
            ['01-0af7651916cd43dd8448eb211c80319c-b9c7c989f97918e1-01'],
            [$vendorKey . '=1'],
            $actualIsTraceParentValid /* <- ref */,
            $actualIsTraceStateValid /* <- ref */
        );
        self::assertNotNull($distTracingData, $dbgMsg);
        self::assertTrue($actualIsTraceParentValid, $dbgMsg);
        self::assertSame($expectedIsValid, $actualIsTraceStateValid, $dbgMsg);
    }

    /**
     * @return array<string>
     */
    public static function generateTraceStateHeaderValues(int $amount): array
    {
        $result = [];
        foreach (RangeUtil::generateUpTo($amount) as $i) {
            $result[] = 'v' . $i;
        }
        return $result;
    }

    // /** @noinspection PhpUnusedPrivateMethodInspection */
    // private static function generateOtherVendorKeyValuePairs(int $firstIndex, int $latIndex): string
    // {
    //     TestCase::assertGreaterThanOrEqual($firstIndex, $latIndex);
    //     $result = '';
    //     foreach (RangeUtil::generateFromToIncluding($firstIndex, $latIndex) as $i) {
    //         if ($i !== $firstIndex) {
    //             $result .= ',';
    //         }
    //         $result .= 'v' . $i . '=_';
    //     }
    //     return $result;
    // }

    // /**
    //  * @param ?string              $elasticVendorValue
    //  * @param array<array<string>> $otherVendorsHeaderValues
    //  *
    //  * @return ?string
    //  */
    // private static function buildOutgoingTraceState(
    //     ?string $elasticVendorValue,
    //     array $otherVendorsHeaderValues
    // ): ?string {
    //     $resultParts = [];
    //     if ($elasticVendorValue !== null) {
    //         $resultParts[] = $elasticVendorValue;
    //     }
    //     $resultParts = array_merge($resultParts, $otherVendorsHeaderValues);
    //     return ArrayUtil::isEmpty($resultParts) ? null : join(',', $resultParts);
    // }
    //
    // /**
    //  * @return iterable<array{array<string>, ?bool, ?string}>
    //  */
    // private static function dataSetsToTestTraceStateMaxPairsCount(): iterable
    // {
    //     $maxPairsCount = HttpDistributedTracing::TRACE_STATE_MAX_PAIRS_COUNT;
    //
    //     yield [
    //         [], /* expectedIsValid: */
    //         null, /* expectedOutgoingTraceState: */
    //         null,
    //     ];
    //
    //     $otherVendors = self::generateOtherVendorKeyValuePairs(1, $maxPairsCount);
    //     yield [[$otherVendors], true, self::buildOutgoingTraceState(null, [$otherVendors])];
    //
    //     $otherVendorsPart1 = self::generateOtherVendorKeyValuePairs(1, $maxPairsCount);
    //     $otherVendorsPart2 = self::generateOtherVendorKeyValuePairs($maxPairsCount + 1, $maxPairsCount + 2);
    //     yield [
    //         [$otherVendorsPart1, $otherVendorsPart2],
    //         true,
    //         self::buildOutgoingTraceState(null, [$otherVendorsPart1]),
    //     ];
    //
    //     $elasticVendor = 'es=s:0';
    //     $otherVendors = self::generateOtherVendorKeyValuePairs(1, $maxPairsCount - 1);
    //     yield [
    //         [$elasticVendor, $otherVendors],
    //         true,
    //         self::buildOutgoingTraceState($elasticVendor, [$otherVendors]),
    //     ];
    //
    //     $elasticVendor = 'es=s:0.0';
    //     $otherVendorsPart1 = self::generateOtherVendorKeyValuePairs(1, $maxPairsCount - 1);
    //     $otherVendorsPart2 = self::generateOtherVendorKeyValuePairs($maxPairsCount, $maxPairsCount + 1);
    //     yield [
    //         [$elasticVendor, $otherVendorsPart1, $otherVendorsPart2],
    //         true,
    //         self::buildOutgoingTraceState($elasticVendor, [$otherVendorsPart1]),
    //     ];
    //
    //     $elasticVendor = 'es=s:0.1234';
    //     $otherVendorsPart1 = self::generateOtherVendorKeyValuePairs(1, $maxPairsCount / 2);
    //     $otherVendorsPart2 = self::generateOtherVendorKeyValuePairs($maxPairsCount / 2 + 1, $maxPairsCount - 1);
    //     yield [
    //         [$otherVendorsPart1, $elasticVendor, $otherVendorsPart2],
    //         true /* <- expectedIsValid */,
    //         self::buildOutgoingTraceState($elasticVendor, [$otherVendorsPart1, $otherVendorsPart2]),
    //     ];
    //
    //     $elasticVendor = 'es=s:0.4321';
    //     $otherVendorsPart1 = self::generateOtherVendorKeyValuePairs(1, $maxPairsCount - 1);
    //     $otherVendorsPart2 = self::generateOtherVendorKeyValuePairs($maxPairsCount, $maxPairsCount + 1);
    //     yield [
    //         [$otherVendorsPart1, $elasticVendor, $otherVendorsPart2],
    //         true,
    //         self::buildOutgoingTraceState($elasticVendor, [$otherVendorsPart1]),
    //     ];
    //
    //     $elasticVendor = 'es=s:1';
    //     $otherVendors = self::generateOtherVendorKeyValuePairs(1, $maxPairsCount - 1);
    //     yield [
    //         [$otherVendors, $elasticVendor],
    //         true,
    //         self::buildOutgoingTraceState($elasticVendor, [$otherVendors]),
    //     ];
    //
    //     $elasticVendor = 'es=s:1.0';
    //     $otherVendors = self::generateOtherVendorKeyValuePairs(1, $maxPairsCount);
    //     yield [[$otherVendors, $elasticVendor], true, self::buildOutgoingTraceState(null, [$otherVendors])];
    //
    //     $elasticVendor = 'es=s:1.000';
    //     $otherVendorsPart1 = self::generateOtherVendorKeyValuePairs(1, $maxPairsCount / 2);
    //     $otherVendorsPart2a = self::generateOtherVendorKeyValuePairs($maxPairsCount / 2 + 1, $maxPairsCount);
    //     $otherVendorsPart2b = self::generateOtherVendorKeyValuePairs($maxPairsCount + 1, $maxPairsCount + 2);
    //     yield [
    //         [$otherVendorsPart1, $otherVendorsPart2a . ', ' . $otherVendorsPart2b, $elasticVendor],
    //         true,
    //         self::buildOutgoingTraceState(null, [$otherVendorsPart1, $otherVendorsPart2a]),
    //     ];
    // }
    //
    // /**
    //  * @return iterable<array{array<string>, ?bool, ?string}>
    //  */
    // public function dataProviderForOutgoingTraceState(): iterable
    // {
    //     yield from self::dataSetsToTestTraceStateMaxPairsCount();
    // }
    //
    // /**
    //  * @dataProvider dataProviderForOutgoingTraceState
    //  *
    //  * @param string[] $traceStateHeaderValues
    //  * @param ?bool   $expectedIsValid
    //  * @param ?string $expectedOutgoingTraceState
    //  */
    // public function testOutgoingTraceState(
    //     array $traceStateHeaderValues,
    //     ?bool $expectedIsValid,
    //     ?string $expectedOutgoingTraceState
    // ): void {
    //     $actualIsTraceParentValid = true;
    //     /** @var ?bool */
    //     $actualIsTraceStateValid = null;
    //     $httpDistributedTracing = new HttpDistributedTracing(AmbientContextForTests::loggerFactory());
    //     $distTracingData = $httpDistributedTracing->parseHeadersImpl(
    //         ['01-0af7651916cd43dd8448eb211c80319c-b9c7c989f97918e1-01'],
    //         $traceStateHeaderValues,
    //         $actualIsTraceParentValid /* <- ref */,
    //         $actualIsTraceStateValid /* <- ref */
    //     );
    //     $dbgMsg = LoggableToString::convert(
    //         [
    //             'traceStateHeaderValues'     => $traceStateHeaderValues,
    //             'expectedIsValid'            => $expectedIsValid,
    //             'expectedOutgoingTraceState' => $expectedOutgoingTraceState,
    //             'actualIsTraceParentValid'   => $actualIsTraceParentValid,
    //             'actualIsTraceStateValid'    => $actualIsTraceStateValid,
    //             'distTracingData'            => $distTracingData,
    //         ],
    //         true /* <- prettyPrint */
    //     );
    //
    //     self::assertNotNull($distTracingData, $dbgMsg);
    //     self::assertTrue($actualIsTraceParentValid, $dbgMsg);
    //     self::assertSame($expectedIsValid, $actualIsTraceStateValid, $dbgMsg);
    //     self::assertSame($expectedOutgoingTraceState, $distTracingData->outgoingTraceState, $dbgMsg);
    // }
}
