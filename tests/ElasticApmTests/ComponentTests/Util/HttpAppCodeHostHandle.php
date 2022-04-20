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

namespace ElasticApmTests\ComponentTests\Util;

use Closure;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Monolog\Test\TestCase;

class HttpAppCodeHostHandle extends AppCodeHostHandle
{
    /** @var HttpServerHandle */
    protected $httpServerHandle;

    public function __construct(
        TestCaseHandle $testCaseHandle,
        HttpAppCodeHostParams $params,
        HttpServerHandle $httpServerHandle
    ) {
        parent::__construct($testCaseHandle, $params);
        $this->httpServerHandle = $httpServerHandle;
    }

    public function getPort(): int
    {
        return $this->httpServerHandle->getPort();
    }

    public function buildDataPerRequest(AppCodeTarget $appCodeTarget): TestInfraDataPerRequest
    {
        return $this->buildRequestParams($appCodeTarget)->dataPerRequest;
    }

    /** @inheritDoc */
    public function sendRequest(AppCodeTarget $appCodeTarget, ?Closure $setParamsFunc = null): void
    {
        $this->sendHttpRequest($appCodeTarget, $setParamsFunc);
    }

    /**
     * @param AppCodeTarget                                $appCodeTarget
     * @param null|Closure(HttpAppCodeRequestParams): void $setParamsFunc
     */
    public function sendHttpRequest(AppCodeTarget $appCodeTarget, ?Closure $setParamsFunc = null): void
    {
        $requestParams = $this->buildRequestParams($appCodeTarget);
        if ($setParamsFunc !== null) {
            $setParamsFunc($requestParams);
        }

        $localLogger = $this->logger->inherit()->addContext('requestParams', $requestParams);

        ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Sending HTTP request to ' . ClassNameUtil::fqToShort(BuiltinHttpServerAppCodeHost::class) . '...'
        );

        $requestSentToAppCode = $this->beforeRequestSent($appCodeTarget, $requestParams);
        $response = TestHttpClientUtil::sendRequest(
            $requestParams->httpRequestMethod,
            $requestParams->urlParts,
            $requestParams->dataPerRequest
        );
        $this->afterRequestSent($requestSentToAppCode);

        TestCase::assertSame($requestParams->expectedHttpResponseStatusCode, $response->getStatusCode());

        ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Successfully sent HTTP request to ' . ClassNameUtil::fqToShort(BuiltinHttpServerAppCodeHost::class)
        );
    }

    private function buildRequestParams(AppCodeTarget $appCodeTarget): HttpAppCodeRequestParams
    {
        $requestParams = new HttpAppCodeRequestParams($appCodeTarget);
        $requestParams->dataPerRequest->serverId = $this->httpServerHandle->getServerId();
        return $requestParams;
    }
}
