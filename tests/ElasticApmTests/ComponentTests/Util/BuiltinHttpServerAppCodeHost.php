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

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\Logger;
use ElasticApmTests\Util\LogCategoryForTests;
use Psr\Http\Message\ResponseInterface;

final class BuiltinHttpServerAppCodeHost extends AppCodeHostBase
{
    use HttpServerProcessTrait;

    /** @var Logger */
    private $logger;

    public function __construct()
    {
        if (self::isStatusCheck()) {
            // We don't want any of the testing infrastructure operations to be recorded as application's APM events
            ElasticApm::getCurrentTransaction()->discard();
        }

        parent::__construct();

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Received request',
            ['URI' => $_SERVER['REQUEST_URI'], 'method' => $_SERVER['REQUEST_METHOD']]
        );
    }

    protected static function isStatusCheck(): bool
    {
        return $_SERVER['REQUEST_URI'] === TestEnvBase::STATUS_CHECK_URI;
    }

    protected function shouldRegisterThisProcessWithResourcesCleaner(): bool
    {
        // We should register with ResourcesCleaner only on the status-check request
        return self::isStatusCheck();
    }

    protected function processConfig(): void
    {
        TestAssertUtil::assertThat(
            !is_null(AmbientContext::testConfig()->sharedDataPerProcess->thisServerId),
            LoggableToString::convert(AmbientContext::testConfig())
        );
        TestAssertUtil::assertThat(
            !is_null(AmbientContext::testConfig()->sharedDataPerProcess->thisServerPort),
            LoggableToString::convert(AmbientContext::testConfig())
        );

        parent::processConfig();

        AmbientContext::reconfigure(
            new RequestHeadersRawSnapshotSource(
                function (string $headerName): ?string {
                    $headerKey = 'HTTP_' . $headerName;
                    return array_key_exists($headerKey, $_SERVER) ? $_SERVER[$headerKey] : null;
                }
            )
        );
    }

    protected function runImpl(): void
    {
        $response = self::verifyServerId(AmbientContext::testConfig()->sharedDataPerRequest->serverId);
        if ($response->getStatusCode() !== HttpConsts::STATUS_OK || self::isStatusCheck()) {
            self::sendResponse($response);
            return;
        }

        $this->callAppCode();
    }

    private static function sendResponse(ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());
        echo $response->getBody();
    }
}
