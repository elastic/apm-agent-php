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

use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\UrlParts;
use PHPUnit\Framework\TestCase;

final class TestProperties implements LoggableInterface
{
    use LoggableTrait;

    /** @var string */
    public $httpMethod = 'GET';

    /** @var UrlParts */
    public $urlParts;

    /** @var int */
    public $expectedStatusCode = HttpConsts::STATUS_OK;

    /** @var ?string */
    public $expectedTransactionName = null;

    /** @var ?string */
    public $transactionType = null;

    /** @var AgentConfigSetter */
    public $agentConfigSetter;

    /** @var SharedDataPerRequest */
    public $sharedDataPerRequest;

    public function __construct()
    {
        $this->agentConfigSetter = new AgentConfigSetterEnvVars();
        $this->agentConfigSetter->set(OptionNames::LOG_LEVEL_SYSLOG, LogLevel::intToName(LogLevel::TRACE));

        $this->sharedDataPerRequest = new SharedDataPerRequest();
        $this->urlParts = TestProperties::newDefaultUrlParts();
    }

    public static function newDefaultUrlParts(): UrlParts
    {
        return (new UrlParts())->scheme('http')->host('localhost')->path('/');
    }

    public function withRoutedAppCode(callable $appCodeClassMethod): self
    {
        TestCase::assertTrue(is_null($this->sharedDataPerRequest->appTopLevelCodeId));

        TestCase::assertTrue(is_callable($appCodeClassMethod));
        TestCase::assertTrue(is_array($appCodeClassMethod));
        /** @noinspection PhpParamsInspection */
        TestCase::assertCount(2, $appCodeClassMethod);

        $this->sharedDataPerRequest->appCodeClass = $appCodeClassMethod[0];
        $this->sharedDataPerRequest->appCodeMethod = $appCodeClassMethod[1];

        return $this;
    }

    public function withTopLevelAppCode(string $topLevelCodeId): self
    {
        TestCase::assertTrue(is_null($this->sharedDataPerRequest->appCodeClass));
        TestCase::assertTrue(is_null($this->sharedDataPerRequest->appCodeMethod));

        $this->sharedDataPerRequest->appTopLevelCodeId = $topLevelCodeId;

        return $this;
    }

    /**
     * @param array<string, mixed> $appCodeArgs
     *
     * @return TestProperties
     */
    public function withAppCodeArgs(array $appCodeArgs): self
    {
        $this->sharedDataPerRequest->appCodeArguments = $appCodeArgs;

        return $this;
    }

    public function withHttpMethod(string $httpMethod): self
    {
        $this->httpMethod = $httpMethod;
        return $this;
    }

    public function withUrlParts(UrlParts $urlParts): self
    {
        $this->urlParts = $urlParts;
        return $this;
    }

    public function withExpectedStatusCode(int $expectedStatusCode): self
    {
        $this->expectedStatusCode = $expectedStatusCode;
        return $this;
    }

    public function withExpectedTransactionName(string $expectedTransactionName): self
    {
        $this->expectedTransactionName = $expectedTransactionName;
        return $this;
    }

    public function withTransactionType(string $transactionType): self
    {
        $this->transactionType = $transactionType;
        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getAllConfiguredAgentOptions(): array
    {
        return $this->agentConfigSetter->optionNameToValue;
    }

    public function getConfiguredAgentOption(string $optName): ?string
    {
        return ArrayUtil::getValueIfKeyExistsElse($optName, $this->agentConfigSetter->optionNameToValue, null);
    }

    public function getConfiguredAgentOptionStringParsed(string $optName): ?string
    {
        $rawValue = $this->getConfiguredAgentOption($optName);
        return $rawValue === null ? null : trim($rawValue);
    }

    public function withAgentConfig(AgentConfigSetter $configSetter): self
    {
        $this->agentConfigSetter = $configSetter;
        return $this;
    }

    public function getAgentConfig(): AgentConfigSetter
    {
        return $this->agentConfigSetter;
    }

    public function tearDown(): void
    {
        $this->agentConfigSetter->tearDown();
    }
}
