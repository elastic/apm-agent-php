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

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\UrlParts;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\CurlHandleWrappedForTests;
use ElasticApmTests\ComponentTests\Util\ExpectedEventCounts;
use ElasticApmTests\ComponentTests\Util\HttpClientUtilForTests;
use ElasticApmTests\ComponentTests\Util\HttpServerHandle;
use ElasticApmTests\ComponentTests\Util\TestInfraDataPerRequest;
use PHPUnit\Framework\TestCase;

/**
 * @group does_not_require_external_services
 */
final class CurlAutoInstrumentationTest extends ComponentTestCaseBase
{
    private const SERVER_PORT_KEY = 'SERVER_PORT';
    private const DATA_PER_REQUEST_FOR_SERVER_SIDE_KEY = 'DATA_PER_REQUEST_FOR_SERVER_SIDE';

    private const SERVER_RESPONSE_HTTP_STATUS = 200;

    private static function assertCurlExtensionIsLoaded(): void
    {
        TestCase::assertTrue(extension_loaded('curl'));
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
        $dataPerRequest = new TestInfraDataPerRequest();
        $dataPerRequest->deserializeFromString($dataPerRequestSerialized);

        /** @var ?CurlHandleWrappedForTests $curlHandle */
        $curlHandle = null;
        try {
            $curlHandle = HttpClientUtilForTests::createCurlHandleToSendRequestToAppCode(
                (new UrlParts())->host(HttpServerHandle::DEFAULT_HOST)->port($serverPort),
                $dataPerRequest,
                self::buildResourcesClientForAppCode()
            );
            $curlExecRetVal = $curlHandle->exec();
            self::assertNotFalse(
                $curlExecRetVal,
                LoggableToString::convert(
                    [
                        '$curlHandle->errno()'         => $curlHandle->errno(),
                        '$curlHandle->error()'         => $curlHandle->error(),
                        '$curlHandle->verboseOutput()' => $curlHandle->verboseOutput(),
                        '$dataPerRequest'              => $dataPerRequest,
                    ]
                )
            );
            $responseStatusCode = $curlHandle->getResponseStatusCode();
            self::assertSame(self::SERVER_RESPONSE_HTTP_STATUS, $responseStatusCode);
        } finally {
            if ($curlHandle !== null) {
                $curlHandle->close();
            }
        }
    }

    public static function appCodeServer(): void
    {
        echo 'Dummy response from ' . __METHOD__;
        http_response_code(self::SERVER_RESPONSE_HTTP_STATUS);
    }

    public function testLocalClientServer(): void
    {
        // TODO: Sergey Kleyman: Implement: CurlAutoInstrumentationTest::testLocalClientServer
        if (PHP_MAJOR_VERSION < 9) {
            self::dummyAssert();
            return;
        }

        $testCaseHandle = $this->getTestCaseHandle();
        $serverAppCode = $testCaseHandle->ensureAdditionalHttpAppCodeHost();
        $clientAppCode = $testCaseHandle->ensureMainAppCodeHost();
        $clientAppCode->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeClient']),
            function (AppCodeRequestParams $reqParams) use ($serverAppCode): void {
                $dataPerRequest = $serverAppCode->buildDataPerRequest(
                    AppCodeTarget::asRouted([__CLASS__, 'appCodeServer'])
                );
                $additionalAppCodeHostPort = $serverAppCode->getHttpServerHandle()->getMainPort();
                $reqParams->setAppCodeArgs(
                    [
                        self::SERVER_PORT_KEY                      => $additionalAppCodeHostPort,
                        self::DATA_PER_REQUEST_FOR_SERVER_SIDE_KEY => $dataPerRequest->serializeToString(),
                    ]
                );
            }
        );

        /**
         * transactions (2): client side + server side
         * spans (1): curl client side
         */
        $testCaseHandle->waitForDataFromAgent((new ExpectedEventCounts())->transactions(2)->spans(1));
    }

    public function testLocalClientExternalServer(): void
    {
        // TODO: Sergey Kleyman: Implement: CurlAutoInstrumentationTest::testLocalClientExternalServer
        self::dummyAssert();
    }
}
