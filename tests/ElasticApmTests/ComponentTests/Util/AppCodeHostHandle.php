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
use Elastic\Apm\Impl\Clock;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use ElasticApmTests\Util\LogCategoryForTests;

abstract class AppCodeHostHandle implements LoggableInterface
{
    use LoggableTrait;

    /** @var TestCaseHandle */
    protected $testCaseHandle;

    /** @var Logger */
    protected $logger;

    /** @var AgentConfigSourceBuilder */
    protected $agentConfigSourceBuilder;

    public function __construct(TestCaseHandle $testCaseHandle, AppCodeHostParams $params)
    {
        $this->testCaseHandle = $testCaseHandle;

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        $this->agentConfigSourceBuilder = new AgentConfigSourceBuilder($params);
    }

    /**
     * @param AppCodeTarget                            $appCodeTarget
     * @param null|Closure(AppCodeRequestParams): void $setParamsFunc
     */
    abstract public function sendRequest(AppCodeTarget $appCodeTarget, ?Closure $setParamsFunc = null): void;

    public function tearDown(): void
    {
        $this->agentConfigSourceBuilder->tearDown();
    }

    protected function beforeRequestSent(AppCodeTarget $target, AppCodeRequestParams $params): RequestSentToAppCode
    {
        $result = new RequestSentToAppCode();
        $result->target = $target;
        $result->params = $params;
        $result->timestampBefore = Clock::singletonInstance()->getSystemClockCurrentTime();
        return $result;
    }

    protected function afterRequestSent(RequestSentToAppCode $requestSentToAppCode): void
    {
        $requestSentToAppCode->timestampAfter = Clock::singletonInstance()->getSystemClockCurrentTime();
        $this->testCaseHandle->addRequestSentToAppCode($requestSentToAppCode);
    }

    /**
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return ['testCaseHandle'];
    }
}
