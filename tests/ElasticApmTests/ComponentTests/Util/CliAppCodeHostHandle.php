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

final class CliAppCodeHostHandle extends AppCodeHostHandle
{
    private const SCRIPT_TO_RUN_APP_CODE_HOST = 'runCliScriptAppCodeHost.php';

    /** @var AppCodeHostParams */
    protected $appCodeHostParams;

    /** @var ResourcesCleanerHandle */
    private $resourcesCleaner;

    /**
     * @param Closure(AppCodeHostParams): void $setParamsFunc
     */
    public function __construct(
        TestCaseHandle $testCaseHandle,
        Closure $setParamsFunc,
        ResourcesCleanerHandle $resourcesCleaner
    ) {
        $this->appCodeHostParams = new AppCodeHostParams();
        $setParamsFunc($this->appCodeHostParams);

        parent::__construct($testCaseHandle, $this->appCodeHostParams);

        $this->resourcesCleaner = $resourcesCleaner;
    }

    /** @inheritDoc */
    public function sendRequest(AppCodeTarget $appCodeTarget, ?Closure $setParamsFunc = null): void
    {
        $requestParams = new AppCodeRequestParams($appCodeTarget);
        if ($setParamsFunc !== null) {
            $setParamsFunc($requestParams);
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Running ' . ClassNameUtil::fqToShort(CliScriptAppCodeHost::class) . '...');

        $cmdLine = TestInfraUtil::buildAppCodePhpCmd($this->agentConfigSourceBuilder->getPhpIniFile())
                   . ' "' . __DIR__ . DIRECTORY_SEPARATOR . self::SCRIPT_TO_RUN_APP_CODE_HOST . '"';

        $envVars = TestInfraUtil::addTestInfraDataPerProcessToEnvVars(
            $this->agentConfigSourceBuilder->getEnvVars(),
            null /* <- $serverId */,
            null /* <- $port */,
            $this->resourcesCleaner,
            $this->appCodeHostParams->agentEphemeralId
        );
        $envVars[AllComponentTestsOptionsMetadata::DATA_PER_REQUEST_OPTION_NAME]
            = $requestParams->dataPerRequest->serializeToString();

        $requestSentToAppCode = $this->beforeRequestSent($appCodeTarget, $requestParams);
        TestProcessUtil::startProcessAndWaitUntilExit($cmdLine, $envVars);
        $this->afterRequestSent($requestSentToAppCode);
    }
}
