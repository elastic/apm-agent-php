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

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Impl\Util\UrlParts;
use Elastic\Apm\Impl\Util\UrlUtil;
use ElasticApmTests\Util\LogCategoryForTests;
use ElasticApmTests\Util\SourceClassLogContext;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class HttpClientUtilForTests
{
    use StaticClassTrait;

    private const CONNECT_TIMEOUT_SECONDS = MockApmServer::DATA_FROM_AGENT_MAX_WAIT_TIME_SECONDS * 2;
    private const TIMEOUT_SECONDS = MockApmServer::DATA_FROM_AGENT_MAX_WAIT_TIME_SECONDS * 2;

    /** @var ?Logger */
    private static $logger = null;

    public static function getLogger(): Logger
    {
        if (self::$logger === null) {
            self::$logger = AmbientContextForTests::loggerFactory()->loggerForClass(
                LogCategoryForTests::TEST_UTIL,
                __NAMESPACE__,
                __CLASS__,
                __FILE__
            );
        }

        return self::$logger;
    }

    /**
     * @param string                  $httpMethod
     * @param UrlParts                $urlParts
     * @param TestInfraDataPerRequest $dataPerRequest
     * @param array<string, string>   $headers
     *
     * @return ResponseInterface
     *
     * @throws GuzzleException
     */
    public static function sendRequest(
        string $httpMethod,
        UrlParts $urlParts,
        TestInfraDataPerRequest $dataPerRequest,
        array $headers = []
    ): ResponseInterface {
        $baseUrl = UrlUtil::buildRequestBaseUrl($urlParts);
        $urlRelPart = UrlUtil::buildRequestMethodArg($urlParts);

        ($loggerProxy = self::getLogger()->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            "Sending HTTP request to `$baseUrl$urlRelPart'...",
            [
                'dataPerRequest' => $dataPerRequest,
                'other headers' => $headers,
            ]
        );

        $client = new Client(['base_uri' => $baseUrl]);

        $response = $client->request(
            $httpMethod,
            $urlRelPart,
            [
                RequestOptions::HEADERS     =>
                    $headers
                    + [
                        RequestHeadersRawSnapshotSource::optionNameToHeaderName(
                            AllComponentTestsOptionsMetadata::DATA_PER_REQUEST_OPTION_NAME
                        ) => $dataPerRequest->serializeToString(),
                    ],
                /*
                 * http://docs.guzzlephp.org/en/stable/request-options.html#http-errors
                 *
                 * http_errors
                 *
                 * Set to false to disable throwing exceptions on an HTTP protocol errors (i.e., 4xx and 5xx responses).
                 * Exceptions are thrown by default when HTTP protocol errors are encountered.
                 */
                RequestOptions::HTTP_ERRORS => false,
                /*
                 * https://docs.guzzlephp.org/en/stable/request-options.html#connect-timeout
                 *
                 * connect-timeout
                 *
                 * Float describing the number of seconds to wait while trying to connect to a server.
                 * Use 0 to wait indefinitely (the default behavior).
                 */
                RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
                /*
                 * https://docs.guzzlephp.org/en/stable/request-options.html#timeout
                 *
                 * timeout
                 *
                 * Float describing the total timeout of the request in seconds.
                 * Use 0 to wait indefinitely (the default behavior).
                 */
                RequestOptions::TIMEOUT => self::TIMEOUT_SECONDS,
            ]
        );

        ($loggerProxy = self::getLogger()->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            "Sent HTTP request to `$baseUrl$urlRelPart' - response status code: " . $response->getStatusCode()
        );

        return $response;
    }

    public static function createCurlHandleToSendRequestToAppCode(
        UrlParts $urlParts,
        TestInfraDataPerRequest $dataPerRequest,
        ResourcesClient $resourcesClient
    ): CurlHandleWrappedForTests {
        $curlInitRetVal = curl_init(UrlUtil::buildFullUrl($urlParts));
        TestCase::assertNotSame(false, $curlInitRetVal);
        $curlHandle = new CurlHandleWrappedForTests($resourcesClient, $curlInitRetVal);
        $dataPerRequestHeaderName = RequestHeadersRawSnapshotSource::optionNameToHeaderName(
            AllComponentTestsOptionsMetadata::DATA_PER_REQUEST_OPTION_NAME
        );
        $dataPerRequestHeaderVal = $dataPerRequest->serializeToString();
        $curlHandle->setOpt(CURLOPT_HTTPHEADER, [$dataPerRequestHeaderName . ': ' . $dataPerRequestHeaderVal]);
        return $curlHandle;
    }
}
