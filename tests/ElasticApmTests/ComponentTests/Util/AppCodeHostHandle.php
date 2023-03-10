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
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;

abstract class AppCodeHostHandle implements LoggableInterface
{
    use LoggableTrait;

    /** @var TestCaseHandle */
    protected $testCaseHandle;

    /** @var AppCodeHostParams */
    public $appCodeHostParams;

    /** @var AgentConfigSourceBuilder */
    protected $agentConfigSourceBuilder;

    /**
     * @param TestCaseHandle           $testCaseHandle
     * @param AppCodeHostParams        $appCodeHostParams
     * @param AgentConfigSourceBuilder $agentConfigSourceBuilder
     */
    public function __construct(
        TestCaseHandle $testCaseHandle,
        AppCodeHostParams $appCodeHostParams,
        AgentConfigSourceBuilder $agentConfigSourceBuilder
    ) {
        $this->testCaseHandle = $testCaseHandle;
        $this->appCodeHostParams = $appCodeHostParams;
        $this->agentConfigSourceBuilder = $agentConfigSourceBuilder;
    }

    /**
     * @param AppCodeTarget                            $appCodeTarget
     * @param null|Closure(AppCodeRequestParams): void $setParamsFunc
     */
    abstract public function sendRequest(AppCodeTarget $appCodeTarget, ?Closure $setParamsFunc = null): void;

    protected function beforeAppCodeInvocation(AppCodeRequestParams $appCodeRequestParams): AppCodeInvocation
    {
        $timestampBefore = AmbientContextForTests::clock()->getSystemClockCurrentTime();
        return new AppCodeInvocation($appCodeRequestParams, $timestampBefore);
    }

    protected function afterAppCodeInvocation(AppCodeInvocation $appCodeInvocation): void
    {
        $appCodeInvocation->after();
        $this->testCaseHandle->addAppCodeInvocation($appCodeInvocation);
    }

    /**
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return ['testCaseHandle'];
    }
}
