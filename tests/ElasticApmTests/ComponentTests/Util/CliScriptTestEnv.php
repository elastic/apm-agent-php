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

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\TransactionContextData;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use ElasticApmTests\Util\LogCategoryForTests;
use PHPUnit\Framework\TestCase;

final class CliScriptTestEnv extends TestEnvBase
{
    private const SCRIPT_TO_RUN_APP_CODE_HOST = 'runCliScriptAppCodeHost.php';

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
        return false;
    }

    protected function sendRequestToInstrumentedApp(TestProperties $testProperties): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Running ' . ClassNameUtil::fqToShort(CliScriptAppCodeHost::class) . '...',
            ['testProperties' => $testProperties]
        );

        TestProcessUtil::startProcessAndWaitUntilExit(
            $testProperties->agentConfigSetter->appCodePhpCmd()
            . ' "' . __DIR__ . DIRECTORY_SEPARATOR . self::SCRIPT_TO_RUN_APP_CODE_HOST . '"',
            self::inheritedEnvVars(/* keepElasticApmEnvVars */ false)
            + [
                TestConfigUtil::envVarNameForTestOption(
                    AllComponentTestsOptionsMetadata::SHARED_DATA_PER_PROCESS_OPTION_NAME
                ) => SerializationUtil::serializeAsJson($this->buildSharedDataPerProcess()),
                TestConfigUtil::envVarNameForTestOption(
                    AllComponentTestsOptionsMetadata::SHARED_DATA_PER_REQUEST_OPTION_NAME
                ) => SerializationUtil::serializeAsJson($testProperties->sharedDataPerRequest),
            ]
            + $testProperties->agentConfigSetter->additionalEnvVars()
        );
    }

    protected function verifyRootTransactionName(TestProperties $testProperties, string $rootTransactionName): void
    {
        parent::verifyRootTransactionName($testProperties, $rootTransactionName);

        if (is_null($testProperties->expectedTransactionName)) {
            TestCase::assertSame(self::SCRIPT_TO_RUN_APP_CODE_HOST, $rootTransactionName);
        }
    }

    protected function verifyRootTransactionType(TestProperties $testProperties, string $rootTransactionType): void
    {
        parent::verifyRootTransactionType($testProperties, $rootTransactionType);

        if (is_null($testProperties->transactionType)) {
            TestCase::assertSame(Constants::TRANSACTION_TYPE_CLI, $rootTransactionType);
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

        TestCase::assertNull($rootTransactionContext->request);
    }
}
