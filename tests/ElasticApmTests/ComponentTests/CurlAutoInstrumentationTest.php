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

use Elastic\Apm\Impl\Util\UrlParts;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\TestHttpClientUtil;
use ElasticApmTests\ComponentTests\Util\TestInfraDataPerRequest;

final class CurlAutoInstrumentationTest extends ComponentTestCaseBase
{
    private const SERVER_PORT_KEY = 'SERVER_PORT';
    private const DATA_PER_REQUEST_FOR_SERVER_SIDE_KEY = 'DATA_PER_REQUEST_FOR_SERVER_SIDE';

    private const SERVER_RESPONSE_HTTP_STATUS = 123;

    private static function assertCurlExtensionIsLoaded(): void
    {
        self::appAssertTrue(extension_loaded('curl'), 'curl is not loaded');
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function appCodeClient(array $args): void
    {
        self::assertCurlExtensionIsLoaded();
        $serverPort = self::getMandatoryAppCodeArg($args, self::SERVER_PORT_KEY);
        self::assertIsInt($serverPort);

        $dataPerRequestSerialized = self::getMandatoryAppCodeArg($args, self::DATA_PER_REQUEST_FOR_SERVER_SIDE_KEY);
        self::assertIsString($dataPerRequestSerialized);
        $dataPerRequest = TestInfraDataPerRequest::deserializeFromString($dataPerRequestSerialized);

        $curlHandle = TestHttpClientUtil::createCurlHandleToSendRequestToAppCode(
            (new UrlParts())->host('localhost')->port($serverPort),
            $dataPerRequest
        );
        $curlExecRetVal = $curlHandle->exec();
        self::assertNotSame(false, $curlExecRetVal);
        $responseStatusCode = $curlHandle->getResponseStatusCode();
        self::assertSame(self::SERVER_RESPONSE_HTTP_STATUS, $responseStatusCode);
        $curlHandle->close();
    }

    public static function appCodeServer(): void
    {
        http_response_code(self::SERVER_RESPONSE_HTTP_STATUS);
    }

    public function testLocalClientServer(): void
    {
        $testHandle = $this->getTestCaseHandle();
        $serverAppCode = $testHandle->ensureAdditionalHttpAppCodeHost();
        $clientAppCode = $testHandle->ensureMainAppCodeHost();
        $clientAppCode->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeClient']),
            function (AppCodeRequestParams $reqParams) use ($serverAppCode): void {
                $dataPerRequest = $serverAppCode->buildDataPerRequest(
                    AppCodeTarget::asRouted([__CLASS__, 'appCodeServer'])
                );
                $reqParams->setAppCodeArgs(
                    [
                        self::SERVER_PORT_KEY => $serverAppCode->getPort(),
                        self::DATA_PER_REQUEST_FOR_SERVER_SIDE_KEY => $dataPerRequest->serializeToString(),
                    ]
                );
            }
        );
        $this->verifyDataFromAgentOneNoSpansTransaction($testHandle);
    }

    public function testLocalClientExternalServer(): void
    {
        // TODO: Sergey Kleyman: Implement: CurlAutoInstrumentationTest::testLocalClientExternalServer
    }
}
