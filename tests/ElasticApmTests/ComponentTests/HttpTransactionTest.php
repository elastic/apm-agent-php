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

use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\UrlParts;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgent;
use ElasticApmTests\ComponentTests\Util\HttpConsts;
use ElasticApmTests\ComponentTests\Util\TestProperties;

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
            self::assertTrue(true);
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
            self::assertTrue(true);
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
            http_response_code($customHttpStatus);
        }
    }

    /**
     * @return array<array<null|int|string>>
     */
    public function dataProviderForHttpStatus(): array
    {
        return [
            [null, 'HTTP 2xx'],
            [200, 'HTTP 2xx'],
            [404, 'HTTP 4xx'],
            [599, 'HTTP 5xx'],
        ];
    }

    /**
     * @dataProvider dataProviderForHttpStatus
     *
     * @param int|null $customHttpStatus
     * @param string   $expectedTxResult
     */
    public function testHttpStatus(?int $customHttpStatus, string $expectedTxResult): void
    {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())
                ->withRoutedAppCode([__CLASS__, 'appCodeForHttpStatus'])
                ->withAppArgs(['customHttpStatus' => $customHttpStatus])
                ->withExpectedStatusCode($customHttpStatus ?? HttpConsts::STATUS_OK),
            function (DataFromAgent $dataFromAgent) use ($expectedTxResult): void {
                $tx = $this->verifyTransactionWithoutSpans($dataFromAgent);
                self::assertSame($this->testEnv->isHttp() ? $expectedTxResult : null, $tx->result);
            }
        );
    }
}
