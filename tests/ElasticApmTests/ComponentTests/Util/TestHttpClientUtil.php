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

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Impl\Util\UrlParts;
use Elastic\Apm\Impl\Util\UrlUtil;
use ElasticApmTests\Util\LogCategoryForTests;
use ElasticApmTests\Util\SourceClassLogContext;
use ElasticApmTests\Util\TestCaseBase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

final class TestHttpClientUtil
{
    use StaticClassTrait;

    /** @var Logger */
    private static $logger;

    public static function getLogger(): Logger
    {
        if (!isset(self::$logger)) {
            self::$logger = AmbientContext::loggerFactory()->loggerForClass(
                LogCategoryForTests::TEST_UTIL,
                __NAMESPACE__,
                __CLASS__,
                __FILE__
            );
        }

        return self::$logger;
    }

    /**
     * @param string                $httpMethod
     * @param UrlParts              $urlParts
     * @param SharedDataPerRequest  $sharedDataPerRequest
     * @param array<string, string> $headers
     *
     * @return ResponseInterface
     *
     * @throws GuzzleException
     */
    public static function sendRequest(
        string $httpMethod,
        UrlParts $urlParts,
        SharedDataPerRequest $sharedDataPerRequest,
        array $headers = []
    ): ResponseInterface {
        $baseUrl = UrlUtil::buildRequestBaseUrl($urlParts);
        $urlRelPart = UrlUtil::buildRequestMethodArg($urlParts);

        TestCaseBase::logAndPrintMessage(
            self::getLogger()->ifDebugLevelEnabled(__LINE__, __FUNCTION__),
            "Sending HTTP request to `${baseUrl}${urlRelPart}'..."
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
                            AllComponentTestsOptionsMetadata::SHARED_DATA_PER_REQUEST_OPTION_NAME
                        ) => SerializationUtil::serializeAsJson($sharedDataPerRequest),
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
            ]
        );

        TestCaseBase::logAndPrintMessage(
            self::getLogger()->ifDebugLevelEnabled(__LINE__, __FUNCTION__),
            "Sent HTTP request to `${baseUrl}${urlRelPart}' - response status code: " . $response->getStatusCode()
        );

        return $response;
    }
}
