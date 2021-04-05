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

use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\TransactionContextData;
use Elastic\Apm\Impl\TransactionContextRequestData;
use Elastic\Apm\Impl\TransactionContextRequestUrlData;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\UrlUtil;
use ElasticApmTests\Util\LogCategoryForTests;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class HttpServerTestEnvBase extends TestEnvBase
{
    /** @var string|null */
    protected $appCodeHostServerId = null;

    /** @var Logger */
    private $logger;

    public function __construct()
    {
        parent::__construct();

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    public function isHttp(): bool
    {
        return true;
    }

    protected function sendRequestToInstrumentedApp(TestProperties $testProperties): void
    {
        $this->ensureAppCodeHostServerRunning($testProperties);
        TestCase::assertNotNull($testProperties->urlParts->port);
        TestCase::assertNotNull($this->appCodeHostServerId);

        $localLogger = $this->logger->inherit()->addAllContext(
            [
                'method'           => $testProperties->httpMethod,
                'urlParts'         => $testProperties->urlParts,
                'requestMethodArg' => UrlUtil::buildRequestMethodArg($testProperties->urlParts),
            ]
        );

        ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Sending HTTP request to ' . ClassNameUtil::fqToShort(BuiltinHttpServerAppCodeHost::class) . '...'
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $response = TestHttpClientUtil::sendRequest(
            $testProperties->httpMethod,
            $testProperties->urlParts,
            SharedDataPerRequest::fromServerId($this->appCodeHostServerId, $testProperties->sharedDataPerRequest)
        );
        if ($response->getStatusCode() !== $testProperties->expectedStatusCode) {
            throw new RuntimeException(
                'HTTP status code does not match the expected one.'
                . ' Expected: ' . $testProperties->expectedStatusCode
                . ', actual: ' . $response->getStatusCode()
            );
        }

        ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Successfully sent HTTP request to ' . ClassNameUtil::fqToShort(BuiltinHttpServerAppCodeHost::class)
        );
    }

    abstract protected function ensureAppCodeHostServerRunning(TestProperties $testProperties): void;

    protected function verifyRootTransaction(TestProperties $testProperties, TransactionData $rootTransaction): void
    {
        parent::verifyRootTransaction($testProperties, $rootTransaction);

        if ($rootTransaction->isSampled) {
            TestCase::assertNotNull($rootTransaction->context);
        }
    }

    protected function verifyRootTransactionName(TestProperties $testProperties, string $rootTransactionName): void
    {
        parent::verifyRootTransactionName($testProperties, $rootTransactionName);

        if (is_null($testProperties->expectedTransactionName)) {
            TestCase::assertSame(
                $testProperties->httpMethod . ' ' . ($testProperties->urlParts->path ?? '/'),
                $rootTransactionName
            );
        }
    }

    protected function verifyRootTransactionType(TestProperties $testProperties, string $rootTransactionType): void
    {
        parent::verifyRootTransactionType($testProperties, $rootTransactionType);

        if (is_null($testProperties->transactionType)) {
            TestCase::assertSame(Constants::TRANSACTION_TYPE_REQUEST, $rootTransactionType);
        }
    }

    protected function verifyRootTransactionContext(
        TestProperties $testProperties,
        ?TransactionContextData $rootTransactionContext
    ): void {
        parent::verifyRootTransactionContext($testProperties, $rootTransactionContext);

        if ($rootTransactionContext === null) {
            return;
        }

        TestCase::assertNotNull($rootTransactionContext->request);
        $this->verifyRootTransactionContextRequest($testProperties, $rootTransactionContext->request);
    }

    protected function verifyRootTransactionContextRequest(
        TestProperties $testProperties,
        TransactionContextRequestData $rootTransactionContextRequest
    ): void {
        /**
         * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L101
         * "required": ["url", "method"]
         */
        TestCase::assertNotNull($rootTransactionContextRequest->method);
        TestCase::assertNotNull($rootTransactionContextRequest->url);

        TestCase::assertSame($testProperties->httpMethod, $rootTransactionContextRequest->method);
        $this->verifyRootTransactionContextRequestUrl($testProperties, $rootTransactionContextRequest->url);
    }

    protected function verifyRootTransactionContextRequestUrl(
        TestProperties $testProperties,
        TransactionContextRequestUrlData $rootTransactionContextRequestUrl
    ): void {
        $fullUrl = UrlUtil::buildFullUrl($testProperties->urlParts);
        TestCase::assertSame($fullUrl, $rootTransactionContextRequestUrl->full);
        TestCase::assertSame($testProperties->urlParts->host, $rootTransactionContextRequestUrl->hostname);
        TestCase::assertSame($testProperties->urlParts->path, $rootTransactionContextRequestUrl->pathname);
        TestCase::assertSame($testProperties->urlParts->port, $rootTransactionContextRequestUrl->port);
        TestCase::assertSame($testProperties->urlParts->scheme, $rootTransactionContextRequestUrl->protocol);
        TestCase::assertSame($fullUrl, $rootTransactionContextRequestUrl->raw);
        TestCase::assertSame(
            $testProperties->urlParts->query,
            $rootTransactionContextRequestUrl->search
        );
    }
}
