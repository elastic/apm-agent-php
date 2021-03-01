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
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use ElasticApmTests\Util\LogCategoryForTests;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class HttpServerTestEnvBase extends TestEnvBase
{
    /** @var string|null */
    protected $appCodeHostServerId = null;

    /** @var int|null */
    protected $appCodeHostServerPort = null;

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
        TestCase::assertNotNull($this->appCodeHostServerPort);
        TestCase::assertNotNull($this->appCodeHostServerId);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Sending HTTP request `' . $testProperties->httpMethod . ' ' . $testProperties->uriPath . '\''
            . ' to ' . ClassNameUtil::fqToShort(BuiltinHttpServerAppCodeHost::class) . '...'
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $response = TestHttpClientUtil::sendHttpRequest(
            $this->appCodeHostServerPort,
            $testProperties->httpMethod,
            $testProperties->uriPath,
            SharedDataPerRequest::fromServerId($this->appCodeHostServerId, $testProperties->sharedDataPerRequest)
        );
        if ($response->getStatusCode() !== $testProperties->expectedStatusCode) {
            throw new RuntimeException(
                'HTTP status code does not match the expected one.'
                . ' Expected: ' . $testProperties->expectedStatusCode
                . ', actual: ' . $response->getStatusCode()
            );
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Successfully sent HTTP request `' . $testProperties->httpMethod . ' ' . $testProperties->uriPath . '\''
            . ' to ' . ClassNameUtil::fqToShort(BuiltinHttpServerAppCodeHost::class) . '...'
        );
    }

    abstract protected function ensureAppCodeHostServerRunning(TestProperties $testProperties): void;

    protected function verifyRootTransactionName(
        TestProperties $testProperties,
        TransactionData $rootTransaction
    ): void {
        parent::verifyRootTransactionName($testProperties, $rootTransaction);

        if (is_null($testProperties->expectedTransactionName)) {
            TestCase::assertSame(
                $testProperties->httpMethod . ' ' . $testProperties->uriPath,
                $rootTransaction->name
            );
        }
    }

    protected function verifyRootTransactionType(
        TestProperties $testProperties,
        TransactionData $rootTransaction
    ): void {
        parent::verifyRootTransactionType($testProperties, $rootTransaction);

        if (is_null($testProperties->transactionType)) {
            TestCase::assertSame(Constants::TRANSACTION_TYPE_REQUEST, $rootTransaction->type);
        }
    }
}
