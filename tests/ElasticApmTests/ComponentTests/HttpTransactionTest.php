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
use Elastic\Apm\Impl\Util\UrlParts;
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\HttpAppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\HttpServerHandle;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class HttpTransactionTest extends ComponentTestCaseBase
{
    /**
     * @return iterable<array<string>>
     */
    public function dataProviderForHttpMethod(): iterable
    {
        return self::adaptToSmoke(
            [
                ['GET'],
                ['POST'],
                ['DELETE'],
            ]
        );
    }

    /**
     * @dataProvider dataProviderForHttpMethod
     *
     * @param string $httpMethod
     */
    public function testHttpMethod(string $httpMethod): void
    {
        if (self::skipIfMainAppCodeHostIsNotHttp()) {
            return;
        }
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainHttpAppCodeHost();
        /** @var ?UrlParts $expectedUrlParts */
        $expectedUrlParts = null;
        $appCodeHost->sendHttpRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeEmpty']),
            function (HttpAppCodeRequestParams $appCodeRequestParams) use ($httpMethod, &$expectedUrlParts): void {
                $appCodeRequestParams->httpRequestMethod = $httpMethod;
                $expectedUrlParts = $appCodeRequestParams->urlParts;
            }
        );
        $dataFromAgent = $this->waitForOneEmptyTransaction($testCaseHandle);
        self::assertNotNull($expectedUrlParts);
        $tx = $dataFromAgent->singleTransaction();
        self::assertSame($httpMethod . ' /', $tx->name);
        self::assertSame(Constants::TRANSACTION_TYPE_REQUEST, $tx->type);
        self::assertNotNull($tx->context);
        self::assertNotNull($tx->context->request);
        self::assertSame($httpMethod, $tx->context->request->method);
        self::assertNotNull($tx->context->request->url);
        self::assertSame(HttpServerHandle::DEFAULT_HOST, $tx->context->request->url->domain);
        self::assertNotNull($expectedUrlParts->port);
        $expectedFullUrl
            = $expectedUrlParts->scheme . '://' . HttpServerHandle::DEFAULT_HOST . ':' . $expectedUrlParts->port . '/';
        self::assertSame($expectedFullUrl, $tx->context->request->url->full);
        self::assertSame($expectedFullUrl, $tx->context->request->url->original);
        self::assertSame($expectedUrlParts->scheme, $tx->context->request->url->protocol);
        self::assertSame('/', $tx->context->request->url->path);
        self::assertSame($expectedUrlParts->port, $tx->context->request->url->port);
        self::assertSame(null, $tx->context->request->url->query);
    }

    /**
     * @return iterable<array{string, ?string}>
     */
    public function dataProviderForUrlParts(): iterable
    {
        foreach (self::adaptToSmoke(['/', '/non_empty_path']) as $path) {
            $queries = [
                null, 'k1=v1', 'k1=v1&k2=v2', 'key_without_value', 'key_without_value=', '=value_without_key',
            ];
            foreach (self::adaptToSmoke($queries) as $query) {
                yield [$path, $query];
            }
        }
    }

    /**
     * @dataProvider dataProviderForUrlParts
     *
     * @param string  $path
     * @param ?string $query
     */
    public function testUrlParts(string $path, ?string $query): void
    {
        if (self::skipIfMainAppCodeHostIsNotHttp()) {
            return;
        }
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainHttpAppCodeHost();
        $appCodeHost->sendHttpRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeEmpty']),
            function (HttpAppCodeRequestParams $appCodeRequestParams) use ($path, $query): void {
                $appCodeRequestParams->urlParts->path($path)->query($query);
            }
        );
        $dataFromAgent = $this->waitForOneEmptyTransaction($testCaseHandle);
        $tx = $dataFromAgent->singleTransaction();
        self::assertNotNull($tx->context);
        self::assertNotNull($tx->context->request);
        self::assertNotNull($tx->context->request->url);
        self::assertSame($path, $tx->context->request->url->path);
        self::assertSame($query, $tx->context->request->url->query);
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function appCodeForHttpStatus(array $args): void
    {
        $customHttpStatus = ArrayUtil::getValueIfKeyExistsElse('customHttpStatus', $args, null);
        if ($customHttpStatus !== null) {
            /** @var int $customHttpStatus */
            http_response_code($customHttpStatus);
        }
    }

    /**
     * @return iterable<array{?int, string, string}>
     */
    public function dataProviderForHttpStatus(): iterable
    {
        return self::adaptToSmoke(
            [
                [null, 'HTTP 2xx', Constants::OUTCOME_SUCCESS],
                [200, 'HTTP 2xx', Constants::OUTCOME_SUCCESS],
                [302, 'HTTP 3xx', Constants::OUTCOME_SUCCESS],
                [404, 'HTTP 4xx', Constants::OUTCOME_SUCCESS],
                [500, 'HTTP 5xx', Constants::OUTCOME_FAILURE],
                [599, 'HTTP 5xx', Constants::OUTCOME_FAILURE],
            ]
        );
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
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost();
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForHttpStatus']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($customHttpStatus): void {
                $appCodeRequestParams->setAppCodeArgs(['customHttpStatus' => $customHttpStatus]);
                if ($appCodeRequestParams instanceof HttpAppCodeRequestParams && $customHttpStatus !== null) {
                    $appCodeRequestParams->expectedHttpResponseStatusCode = $customHttpStatus;
                }
            }
        );
        $dataFromAgent = $this->waitForOneEmptyTransaction($testCaseHandle);
        $tx = $dataFromAgent->singleTransaction();
        self::assertSame(self::isMainAppCodeHostHttp() ? $expectedTxResult : null, $tx->result);
        self::assertSame(self::isMainAppCodeHostHttp() ? $expectedTxOutcome : null, $tx->outcome);
    }

    public static function appCodeForSetResultManually(): void
    {
        ElasticApm::getCurrentTransaction()->setResult('my manually set result');
    }

    public function testSetResultManually(): void
    {
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost();
        $appCodeHost->sendRequest(AppCodeTarget::asRouted([__CLASS__, 'appCodeForSetResultManually']));
        $dataFromAgent = $this->waitForOneEmptyTransaction($testCaseHandle);
        $tx = $dataFromAgent->singleTransaction();
        self::assertSame('my manually set result', $tx->result);
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
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost();
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForSetOutcomeManually']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($shouldSetOutcomeManually): void {
                $appCodeRequestParams->setAppCodeArgs(['shouldSetOutcomeManually' => $shouldSetOutcomeManually]);
            }
        );
        $dataFromAgent = $this->waitForOneEmptyTransaction($testCaseHandle);
        $tx = $dataFromAgent->singleTransaction();
        $expectedOutcome = $shouldSetOutcomeManually
            ? Constants::OUTCOME_UNKNOWN
            : (self::isMainAppCodeHostHttp() ? Constants::OUTCOME_SUCCESS : null);
        self::assertSame($expectedOutcome, $tx->outcome);
    }

    /**
     * @return iterable<array{string, UrlParts, string}>
     */
    private static function dataProviderForTestUrlGroupsConfigImpl(): iterable
    {
        yield [
            '/foo/*/bar',
            (new UrlParts())->path('/foo/12345/bar'),
            'GET /foo/*/bar',
        ];

        yield [
            '/foo/*/bar',
            (new UrlParts())->path('/foo/12345/bar/98765'),
            'GET /foo/12345/bar/98765',
        ];

        yield [
            '/foo/*/bar/*',
            (new UrlParts())->path('/foo/12345/bar/98765'),
            'GET /foo/*/bar/*',
        ];

        yield [
            '/foo/*/bar/*',
            (new UrlParts())->path('/foo/12345/bar/98765/4321'),
            'GET /foo/*/bar/*',
        ];

        /////////////////////////////////
        ///
        /// Case sensitive
        ///

        yield [
            '(?-i)/foo_a/*/bar_a/*, /foo_b/*/bar_b/*',
            (new UrlParts())->path('/foo_a/12345/bar_a/98765'),
            'GET /foo_a/*/bar_a/*',
        ];

        yield [
            '(?-i)/foo_a/*/bar_a/*, /foo_b/*/bar_b/*',
            (new UrlParts())->path('/FOO_A/12345/BAR_A/98765'),
            'GET /FOO_A/12345/BAR_A/98765',
        ];

        yield [
            '(?-i)/foo_a/*/bar_a/*, /foo_b/*/bar_b/*',
            (new UrlParts())->path('/FOO_B/12345/BAR_B/98765'),
            'GET /foo_b/*/bar_b/*',
        ];

        /////////////////////////////////
        ///
        /// With query
        ///

        yield [
            '/foo/*/bar',
            (new UrlParts())->path('/foo/12345/bar')->query('query_key=query_val'),
            'GET /foo/*/bar',
        ];

        yield [
            '/foo/*/bar?query_key=query_val',
            (new UrlParts())->path('/foo/12345/bar')->query('query_key=query_val'),
            'GET /foo/12345/bar',
        ];
    }

    /**
     * @return iterable<array{string, UrlParts, string}>
     */
    public function dataProviderForTestUrlGroupsConfig(): iterable
    {
        return self::adaptToSmoke(self::dataProviderForTestUrlGroupsConfigImpl());
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
        if (self::skipIfMainAppCodeHostIsNotHttp()) {
            return;
        }
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($urlGroupsConfigVal): void {
                $appCodeParams->setAgentOption(OptionNames::URL_GROUPS, $urlGroupsConfigVal);
            }
        );
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeEmpty']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($urlParts, $expectedTxName): void {
                $appCodeRequestParams->expectedTransactionName->setValue($expectedTxName);
                self::assertInstanceOf(HttpAppCodeRequestParams::class, $appCodeRequestParams);
                /** @var HttpAppCodeRequestParams $appCodeRequestParams */
                $appCodeRequestParams->urlParts->path($urlParts->path)->query($urlParts->query);
            }
        );
        $dataFromAgent = $this->waitForOneEmptyTransaction($testCaseHandle);
        $tx = $dataFromAgent->singleTransaction();
        self::assertSame($expectedTxName, $tx->name);
    }

    private const IGNORED_TX_REPLACEMENT_NAME = 'ignored TX replacement name';
    private const IGNORED_TX_REPLACEMENT_TYPE = 'ignored TX replacement type';
    private const TRANSACTION_IGNORE_URLS_CUSTOM_HTTP_STATUS = 217;

    /**
     * @return iterable<array{string, UrlParts, bool}>
     */
    private static function dataProviderForTestTransactionIgnoreUrlsConfigImpl(): iterable
    {
        yield [
            '/foo/*/bar',
            (new UrlParts())->path('/foo/12345/bar'),
            true /* <- expectedShouldBeIgnored */,
        ];

        yield [
            '/foo/*/bar',
            (new UrlParts())->path('/foo/12345/bar/6789'),
            false /* <- expectedShouldBeIgnored */,
        ];

        yield [
            '/foo/*/bar/*',
            (new UrlParts())->path('/foo/12345/bar/6789'),
            true /* <- expectedShouldBeIgnored */,
        ];

        /////////////////////////////////
        ///
        /// With query
        ///

        yield [
            '/foo/*/bar',
            (new UrlParts())->path('/foo/12345/bar')->query('query_key=query_val'),
            true /* <- expectedShouldBeIgnored */,
        ];

        yield [
            '/foo/*/bar?query_key=query_val',
            (new UrlParts())->path('/foo/12345/bar')->query('query_key=query_val'),
            false /* <- expectedShouldBeIgnored */,
        ];
    }

    /**
     * @return iterable<array{string, UrlParts, bool}>
     */
    public function dataProviderForTestTransactionIgnoreUrlsConfig(): iterable
    {
        return self::adaptToSmoke(self::dataProviderForTestTransactionIgnoreUrlsConfigImpl());
    }

    /**
     * @param array<string, mixed> $appCodeArgs
     */
    public static function appCodeTransactionIgnoreUrlsConfig(array $appCodeArgs): void
    {
        $expectedShouldBeIgnored = self::getMandatoryAppCodeArg($appCodeArgs, 'expectedShouldBeIgnored');
        self::assertSame($expectedShouldBeIgnored, ElasticApm::getCurrentTransaction()->isNoop());

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
     * @param bool     $expectedShouldBeIgnored
     */
    public function testTransactionIgnoreUrlsConfig(
        string $transactionIgnoreUrlsConfigVal,
        UrlParts $urlParts,
        bool $expectedShouldBeIgnored
    ): void {
        if (self::skipIfMainAppCodeHostIsNotHttp()) {
            return;
        }
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($transactionIgnoreUrlsConfigVal): void {
                $appCodeParams->setAgentOption(OptionNames::TRANSACTION_IGNORE_URLS, $transactionIgnoreUrlsConfigVal);
            }
        );
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeTransactionIgnoreUrlsConfig']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($urlParts, $expectedShouldBeIgnored): void {
                $appCodeRequestParams->setAppCodeArgs(['expectedShouldBeIgnored' => $expectedShouldBeIgnored]);
                $appCodeRequestParams->shouldVerifyRootTransaction = !$expectedShouldBeIgnored;
                if ($appCodeRequestParams instanceof HttpAppCodeRequestParams) {
                    $appCodeRequestParams->urlParts->path($urlParts->path)->query($urlParts->query);
                    $appCodeRequestParams->expectedHttpResponseStatusCode
                        = self::TRANSACTION_IGNORE_URLS_CUSTOM_HTTP_STATUS;
                }
            }
        );
        $dataFromAgent = $this->waitForOneEmptyTransaction($testCaseHandle);
        $tx = $dataFromAgent->singleTransaction();
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
}
