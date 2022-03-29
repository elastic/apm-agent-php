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

namespace ElasticApmTests\ComponentTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\UrlParts;
use Elastic\Apm\TransactionInterface;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgent;
use ElasticApmTests\ComponentTests\Util\HttpConsts;
use ElasticApmTests\ComponentTests\Util\TestProperties;
use RuntimeException;

final class HttpTransactionTest extends ComponentTestCaseBase
{
    /**
     * @return array<array<?string>>
     */
    public function dataProviderForHttpMethod(): array
    {
        return [
            ['GET'],
            ['POST'],
            ['DELETE'],
        ];
    }

    /**
     * @dataProvider dataProviderForHttpMethod
     *
     * @param string $httpMethod
     */
    public function testHttpMethod(string $httpMethod): void
    {
        if (!$this->testEnv->isHttp()) {
            self::dummyAssert();
            return;
        }

        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())
                ->withRoutedAppCode([__CLASS__, 'appCodeEmpty'])
                ->withHttpMethod($httpMethod),
            function (DataFromAgent $dataFromAgent): void {
                $this->verifyTransactionWithoutSpans($dataFromAgent);
                /**
                 * @see HttpServerTestEnvBase::verifyRootTransactionName()
                 * @see HttpServerTestEnvBase::verifyRootTransactionType()
                 * @see HttpServerTestEnvBase::verifyRootTransactionContext()
                 */
            }
        );
    }

    /**
     * @return iterable<array<UrlParts>>
     */
    public function dataProviderForUrlParts(): iterable
    {
        foreach (['/', '/non_empty_path'] as $path) {
            $queries = [
                null, 'k1=v1', 'k1=v1&k2=v2', 'key_without_value', 'key_without_value=', '=value_without_key',
            ];
            foreach ($queries as $query) {
                yield [TestProperties::newDefaultUrlParts()->path($path)->query($query)];
            }
        }
    }

    /**
     * @dataProvider dataProviderForUrlParts
     *
     * @param UrlParts $urlParts
     */
    public function testUrlParts(UrlParts $urlParts): void
    {
        if (!$this->testEnv->isHttp()) {
            self::dummyAssert();
            return;
        }

        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())
                ->withRoutedAppCode([__CLASS__, 'appCodeEmpty'])
                ->withUrlParts($urlParts),
            function (DataFromAgent $dataFromAgent): void {
                $this->verifyTransactionWithoutSpans($dataFromAgent);
                /** @see HttpServerTestEnvBase::verifyRootTransactionName */
            }
        );
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function appCodeForHttpStatus(array $args): void
    {
        $customHttpStatus = ArrayUtil::getValueIfKeyExistsElse('customHttpStatus', $args, null);
        if (!is_null($customHttpStatus)) {
            /** @var int $customHttpStatus */
            http_response_code($customHttpStatus);
        }
    }

    /**
     * @return array<array<null|int|string>>
     */
    public function dataProviderForHttpStatus(): array
    {
        return [
            [null, 'HTTP 2xx', Constants::OUTCOME_SUCCESS],
            [200, 'HTTP 2xx', Constants::OUTCOME_SUCCESS],
            [302, 'HTTP 3xx', Constants::OUTCOME_SUCCESS],
            [404, 'HTTP 4xx', Constants::OUTCOME_SUCCESS],
            [500, 'HTTP 5xx', Constants::OUTCOME_FAILURE],
            [599, 'HTTP 5xx', Constants::OUTCOME_FAILURE],
        ];
    }

    /**
     * @dataProvider dataProviderForHttpStatus
     *
     * @param int|null $customHttpStatus
     * @param string   $expectedTxResult
     * @param string   $expectedTxOutcome
     */
    public function testHttpStatus(?int $customHttpStatus, string $expectedTxResult, string $expectedTxOutcome): void
    {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())
                ->withRoutedAppCode([__CLASS__, 'appCodeForHttpStatus'])
                ->withAppCodeArgs(['customHttpStatus' => $customHttpStatus])
                ->withExpectedStatusCode($customHttpStatus ?? HttpConsts::STATUS_OK),
            function (DataFromAgent $dataFromAgent) use ($expectedTxResult, $expectedTxOutcome): void {
                $tx = $this->verifyTransactionWithoutSpans($dataFromAgent);
                self::assertSame($this->testEnv->isHttp() ? $expectedTxResult : null, $tx->result);
                self::assertSame($this->testEnv->isHttp() ? $expectedTxOutcome : null, $tx->outcome);
            }
        );
    }

    public static function appCodeForSetResultManually(): void
    {
        ElasticApm::getCurrentTransaction()->setResult('my manually set result');
    }

    public function testSetResultManually(): void
    {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())
                ->withRoutedAppCode([__CLASS__, 'appCodeForSetResultManually'])
                ->withExpectedStatusCode(HttpConsts::STATUS_OK),
            function (DataFromAgent $dataFromAgent): void {
                $tx = $this->verifyTransactionWithoutSpans($dataFromAgent);
                self::assertSame('my manually set result', $tx->result);
            }
        );
    }

    /**
     * @param array<string, mixed> $appCodeArgs
     */
    public static function appCodeForSetOutcomeManually(array $appCodeArgs): void
    {
        $shouldSetOutcomeManually = self::getMandatoryAppCodeArg($appCodeArgs, 'shouldSetOutcomeManually');
        if ($shouldSetOutcomeManually) {
            ElasticApm::getCurrentTransaction()->setOutcome(Constants::OUTCOME_UNKNOWN);
        }
    }

    /**
     * @dataProvider boolDataProvider
     *
     * @param bool $shouldSetOutcomeManually
     */
    public function testSetOutcomeManually(bool $shouldSetOutcomeManually): void
    {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())
                ->withRoutedAppCode([__CLASS__, 'appCodeForSetOutcomeManually'])
                ->withAppCodeArgs(['shouldSetOutcomeManually' => $shouldSetOutcomeManually])
                ->withExpectedStatusCode(HttpConsts::STATUS_OK),
            function (DataFromAgent $dataFromAgent) use ($shouldSetOutcomeManually): void {
                $tx = $this->verifyTransactionWithoutSpans($dataFromAgent);
                self::assertSame(
                    $shouldSetOutcomeManually
                        ? Constants::OUTCOME_UNKNOWN
                        : ($this->testEnv->isHttp() ? Constants::OUTCOME_SUCCESS : null),
                    $tx->outcome
                );
            }
        );
    }

    /**
     * @return iterable<array{string, UrlParts, string}>
     */
    public function dataProviderForTestUrlGroupsConfig(): iterable
    {
        yield [
            '/foo/*/bar',
            TestProperties::newDefaultUrlParts()->path('/foo/12345/bar'),
            'GET /foo/*/bar',
        ];

        yield [
            '/foo/*/bar',
            TestProperties::newDefaultUrlParts()->path('/foo/12345/bar/98765'),
            'GET /foo/12345/bar/98765',
        ];

        yield [
            '/foo/*/bar/*',
            TestProperties::newDefaultUrlParts()->path('/foo/12345/bar/98765'),
            'GET /foo/*/bar/*',
        ];

        yield [
            '/foo/*/bar/*',
            TestProperties::newDefaultUrlParts()->path('/foo/12345/bar/98765/4321'),
            'GET /foo/*/bar/*',
        ];

        /////////////////////////////////
        ///
        /// Case sensitive
        ///

        yield [
            '(?-i)/foo_a/*/bar_a/*, /foo_b/*/bar_b/*',
            TestProperties::newDefaultUrlParts()->path('/foo_a/12345/bar_a/98765'),
            'GET /foo_a/*/bar_a/*',
        ];

        yield [
            '(?-i)/foo_a/*/bar_a/*, /foo_b/*/bar_b/*',
            TestProperties::newDefaultUrlParts()->path('/FOO_A/12345/BAR_A/98765'),
            'GET /FOO_A/12345/BAR_A/98765',
        ];

        yield [
            '(?-i)/foo_a/*/bar_a/*, /foo_b/*/bar_b/*',
            TestProperties::newDefaultUrlParts()->path('/FOO_B/12345/BAR_B/98765'),
            'GET /foo_b/*/bar_b/*',
        ];

        /////////////////////////////////
        ///
        /// With query
        ///

        yield [
            '/foo/*/bar',
            TestProperties::newDefaultUrlParts()->path('/foo/12345/bar')->query('query_key=query_val'),
            'GET /foo/*/bar',
        ];

        yield [
            '/foo/*/bar?query_key=query_val',
            TestProperties::newDefaultUrlParts()->path('/foo/12345/bar')->query('query_key=query_val'),
            'GET /foo/12345/bar',
        ];
    }

    /**
     * @dataProvider dataProviderForTestUrlGroupsConfig
     *
     * @param string   $urlGroupsConfigVal
     * @param UrlParts $urlParts
     * @param string   $expectedTxName
     */
    public function testUrlGroupsConfig(
        string $urlGroupsConfigVal,
        UrlParts $urlParts,
        string $expectedTxName
    ): void {
        if (!$this->testEnv->isHttp()) {
            self::dummyAssert();
            return;
        }

        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())
                ->withAgentConfig($this->randomConfigSetter()->set(OptionNames::URL_GROUPS, $urlGroupsConfigVal))
                ->withRoutedAppCode([__CLASS__, 'appCodeEmpty'])
                ->withUrlParts($urlParts)
                ->withExpectedTransactionName($expectedTxName),
            function (DataFromAgent $dataFromAgent): void {
                $this->verifyTransactionWithoutSpans($dataFromAgent);
                /** @see HttpServerTestEnvBase::verifyRootTransactionName */
            }
        );
    }

    private const APP_CODE_ARGS_KEY_EXPECTED_SHOULD_BE_IGNORED = 'APP_CODE_ARGS_KEY_EXPECTED_SHOULD_BE_IGNORED';
    private const IGNORED_TX_REPLACEMENT_NAME = 'ignored TX replacement name';
    private const IGNORED_TX_REPLACEMENT_TYPE = 'ignored TX replacement type';
    private const TRANSACTION_IGNORE_URLS_CUSTOM_HTTP_STATUS = 217;

    /**
     * @return iterable<array{string, UrlParts, bool}>
     */
    public function dataProviderForTestTransactionIgnoreUrlsConfig(): iterable
    {
        yield [
            '/foo/*/bar',
            TestProperties::newDefaultUrlParts()->path('/foo/12345/bar'),
            true /* <- expectedShouldBeIgnored */,
        ];

        yield [
            '/foo/*/bar',
            TestProperties::newDefaultUrlParts()->path('/foo/12345/bar/6789'),
            false /* <- expectedShouldBeIgnored */,
        ];

        yield [
            '/foo/*/bar/*',
            TestProperties::newDefaultUrlParts()->path('/foo/12345/bar/6789'),
            true /* <- expectedShouldBeIgnored */,
        ];

        /////////////////////////////////
        ///
        /// With query
        ///

        yield [
            '/foo/*/bar',
            TestProperties::newDefaultUrlParts()->path('/foo/12345/bar')->query('query_key=query_val'),
            true /* <- expectedShouldBeIgnored */,
        ];

        yield [
            '/foo/*/bar?query_key=query_val',
            TestProperties::newDefaultUrlParts()->path('/foo/12345/bar')->query('query_key=query_val'),
            false /* <- expectedShouldBeIgnored */,
        ];
    }

    /**
     * @param array<string, mixed> $appCodeArgs
     */
    public static function appCodeTransactionIgnoreUrlsConfig(array $appCodeArgs): void
    {
        $expectedShouldBeIgnored
            = self::getMandatoryAppCodeArg($appCodeArgs, self::APP_CODE_ARGS_KEY_EXPECTED_SHOULD_BE_IGNORED);
        $isTxNoop = ElasticApm::getCurrentTransaction()->isNoop();

        if ($expectedShouldBeIgnored !== $isTxNoop) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    '$expectedShouldBeIgnored is not equal to $isTxNoop',
                    [
                        'expectedShouldBeIgnored' => $expectedShouldBeIgnored,
                        'isTxNoop'                => $isTxNoop,
                    ]
                )
            );
        }

        if ($expectedShouldBeIgnored) {
            ElasticApm::captureTransaction(
                self::IGNORED_TX_REPLACEMENT_NAME,
                self::IGNORED_TX_REPLACEMENT_TYPE,
                function () {
                }
            );
        }

        http_response_code(self::TRANSACTION_IGNORE_URLS_CUSTOM_HTTP_STATUS);
    }

    /**
     * @dataProvider dataProviderForTestTransactionIgnoreUrlsConfig
     *
     * @param string   $transactionIgnoreUrlsConfigVal
     * @param UrlParts $urlParts
     * @param bool   $expectedShouldBeIgnored
     */
    public function testTransactionIgnoreUrlsConfig(
        string $transactionIgnoreUrlsConfigVal,
        UrlParts $urlParts,
        bool $expectedShouldBeIgnored
    ): void {
        if (!$this->testEnv->isHttp()) {
            self::dummyAssert();
            return;
        }

        $configSetter = $this->randomConfigSetter();
        $configSetter->set(OptionNames::TRANSACTION_IGNORE_URLS, $transactionIgnoreUrlsConfigVal);
        $testProperties = (new TestProperties())
            ->withAgentConfig($configSetter)
            ->withRoutedAppCode([__CLASS__, 'appCodeTransactionIgnoreUrlsConfig'])
            ->withUrlParts($urlParts)
            ->withAppCodeArgs([self::APP_CODE_ARGS_KEY_EXPECTED_SHOULD_BE_IGNORED => $expectedShouldBeIgnored])
            ->withExpectedStatusCode(self::TRANSACTION_IGNORE_URLS_CUSTOM_HTTP_STATUS);
        if ($expectedShouldBeIgnored) {
            $testProperties->shouldVerifyRootTransaction(false);
        }

        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            $testProperties,
            function (DataFromAgent $dataFromAgent) use ($expectedShouldBeIgnored): void {
                $tx = $this->verifyTransactionWithoutSpans($dataFromAgent);
                if ($expectedShouldBeIgnored) {
                    $this->assertSame(self::IGNORED_TX_REPLACEMENT_NAME, $tx->name);
                    $this->assertSame(self::IGNORED_TX_REPLACEMENT_TYPE, $tx->type);
                    $this->assertNull($tx->result);
                    $this->assertNull($tx->outcome);
                } else {
                    self::assertSame('HTTP 2xx', $tx->result);
                    self::assertSame(Constants::OUTCOME_SUCCESS, $tx->outcome);
                }
            }
        );
    }
}
