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
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use ElasticApmTests\Util\FileUtilForTests;
use ElasticApmTests\Util\LogCategoryForTests;

final class CliScriptAppCodeHostHandle extends AppCodeHostHandle
{
    /** @var ResourcesCleanerHandle */
    private $resourcesCleaner;

    /** @var Logger */
    private $logger;

    /**
     * @param TestCaseHandle                   $testCaseHandle
     * @param Closure(AppCodeHostParams): void $setParamsFunc
     * @param ResourcesCleanerHandle           $resourcesCleaner
     * @param string                           $dbgInstanceName
     */
    public function __construct(
        TestCaseHandle $testCaseHandle,
        Closure $setParamsFunc,
        ResourcesCleanerHandle $resourcesCleaner,
        string $dbgInstanceName
    ) {
        $dbgProcessName = ClassNameUtil::fqToShort(CliScriptAppCodeHost::class) . '(' . $dbgInstanceName . ')';
        $appCodeHostParams = new AppCodeHostParams($dbgProcessName);
        $appCodeHostParams->spawnedProcessInternalId = InfraUtilForTests::generateSpawnedProcessInternalId();
        $setParamsFunc($appCodeHostParams);

        $agentConfigSourceBuilder = new AgentConfigSourceBuilder($resourcesCleaner->getClient(), $appCodeHostParams);
        parent::__construct($testCaseHandle, $appCodeHostParams, $agentConfigSourceBuilder);

        $this->resourcesCleaner = $resourcesCleaner;

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    /** @inheritDoc */
    public function sendRequest(AppCodeTarget $appCodeTarget, ?Closure $setParamsFunc = null): void
    {
        $requestParams = new CliScriptAppCodeRequestParams($appCodeTarget);
        if ($setParamsFunc !== null) {
            $setParamsFunc($requestParams);
        }
        $this->setAppCodeRequestParamsExpected($requestParams);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Starting...');

        $cmdLine = InfraUtilForTests::buildAppCodePhpCmd($this->agentConfigSourceBuilder->getPhpIniFile())
                   . ' "' . FileUtilForTests::listToPath([__DIR__, $requestParams->scriptToRunAppCodeHost]) . '"';
        foreach ($requestParams->scriptToRunAppCodeHostArgs as $scriptToRunAppCodeHostArg) {
            $cmdLine .= ' ' . $scriptToRunAppCodeHostArg;
        }

        $envVars = InfraUtilForTests::addTestInfraDataPerProcessToEnvVars(
            $this->agentConfigSourceBuilder->getEnvVars(EnvVarUtilForTests::getAll()),
            $this->appCodeHostParams->spawnedProcessInternalId,
            [] /* <- targetServerPorts */,
            $this->resourcesCleaner,
            $this->appCodeHostParams->dbgProcessName
        );
        $dataPerRequestOptName = AllComponentTestsOptionsMetadata::DATA_PER_REQUEST_OPTION_NAME;
        $dataPerRequestEnvVarName = ConfigUtilForTests::testOptionNameToEnvVarName($dataPerRequestOptName);
        $envVars[$dataPerRequestEnvVarName] = $requestParams->dataPerRequest->serializeToString();

        $appCodeInvocation = $this->beforeAppCodeInvocation($requestParams);
        ProcessUtilForTests::startProcessAndWaitUntilExit($cmdLine, $envVars);
        $this->afterAppCodeInvocation($appCodeInvocation);
    }

    private function setAppCodeRequestParamsExpected(CliScriptAppCodeRequestParams $appCodeRequestParams): void
    {
        $appCodeRequestParams->expectedTransactionName->setValueIfNotSet($appCodeRequestParams->scriptToRunAppCodeHost);
        $appCodeRequestParams->expectedTransactionType->setValueIfNotSet(Constants::TRANSACTION_TYPE_CLI);
    }
}
