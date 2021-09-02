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

    protected function sendRequestToInstrumentedApp(): void
    {
        TestCase::assertTrue(isset($this->testProperties));

        $this->ensureAppCodeHostServerRunning();
        TestCase::assertNotNull($this->testProperties->urlParts->port);
        TestCase::assertNotNull($this->appCodeHostServerId);

        $localLogger = $this->logger->inherit()->addAllContext(
            [
                'method'           => $this->testProperties->httpMethod,
                'urlParts'         => $this->testProperties->urlParts,
                'requestMethodArg' => UrlUtil::buildRequestMethodArg($this->testProperties->urlParts),
            ]
        );

        ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Sending HTTP request to ' . ClassNameUtil::fqToShort(BuiltinHttpServerAppCodeHost::class) . '...'
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $response = TestHttpClientUtil::sendRequest(
            $this->testProperties->httpMethod,
            $this->testProperties->urlParts,
            SharedDataPerRequest::fromServerId($this->appCodeHostServerId, $this->testProperties->sharedDataPerRequest)
        );
        if ($response->getStatusCode() !== $this->testProperties->expectedStatusCode) {
            throw new RuntimeException(
                'HTTP status code does not match the expected one.'
                . ' Expected: ' . $this->testProperties->expectedStatusCode
                . ', actual: ' . $response->getStatusCode()
            );
        }

        ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Successfully sent HTTP request to ' . ClassNameUtil::fqToShort(BuiltinHttpServerAppCodeHost::class)
        );
    }

    abstract protected function ensureAppCodeHostServerRunning(): void;

    protected function verifyRootTransactionEx(TransactionData $rootTransaction): void
    {
        if ($rootTransaction->isSampled) {
            TestCase::assertNotNull($rootTransaction->context);
        }
    }

    protected function verifyRootTransactionName(string $rootTransactionName): void
    {
        parent::verifyRootTransactionName($rootTransactionName);

        if ($this->testProperties->expectedTransactionName === null) {
            TestCase::assertSame(
                $this->testProperties->httpMethod . ' ' . ($this->testProperties->urlParts->path ?? '/'),
                $rootTransactionName
            );
        }
    }

    protected function verifyRootTransactionType(string $rootTransactionType): void
    {
        parent::verifyRootTransactionTypeImpl($rootTransactionType, Constants::TRANSACTION_TYPE_REQUEST);
    }

    protected function verifyRootTransactionContext(?TransactionContextData $rootTransactionContext): void
    {
        parent::verifyRootTransactionContext($rootTransactionContext);

        if ($rootTransactionContext === null) {
            return;
        }

        TestCase::assertNotNull($rootTransactionContext->request);
        $this->verifyRootTransactionContextRequest($rootTransactionContext->request);
    }

    protected function verifyRootTransactionContextRequest(
        TransactionContextRequestData $rootTransactionContextRequest
    ): void {
        /**
         * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L101
         * "required": ["url", "method"]
         */
        TestCase::assertNotNull($rootTransactionContextRequest->method);
        TestCase::assertNotNull($rootTransactionContextRequest->url);

        TestCase::assertSame($this->testProperties->httpMethod, $rootTransactionContextRequest->method);
        $this->verifyRootTransactionContextRequestUrl($this->testProperties, $rootTransactionContextRequest->url);
    }

    protected function verifyRootTransactionContextRequestUrl(
        TestProperties $testProperties,
        TransactionContextRequestUrlData $rootTransactionContextRequestUrl
    ): void {
        $fullUrl = UrlUtil::buildFullUrl($testProperties->urlParts);
        TestCase::assertSame($testProperties->urlParts->host, $rootTransactionContextRequestUrl->domain);
        TestCase::assertSame($fullUrl, $rootTransactionContextRequestUrl->full);
        TestCase::assertSame($fullUrl, $rootTransactionContextRequestUrl->original);
        TestCase::assertSame($testProperties->urlParts->path, $rootTransactionContextRequestUrl->path);
        TestCase::assertSame($testProperties->urlParts->port, $rootTransactionContextRequestUrl->port);
        TestCase::assertSame($testProperties->urlParts->scheme, $rootTransactionContextRequestUrl->protocol);
        TestCase::assertSame(
            $testProperties->urlParts->query,
            $rootTransactionContextRequestUrl->query
        );
    }
}
