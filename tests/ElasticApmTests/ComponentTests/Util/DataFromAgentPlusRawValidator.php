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

use Elastic\Apm\Impl\BackendComm\EventSender;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Tracer;
use ElasticApmTests\Util\DataFromAgentValidator;
use ElasticApmTests\Util\AssertValidTrait;
use ElasticApmTests\Util\MetadataValidator;
use PHPUnit\Framework\TestCase;

final class DataFromAgentPlusRawValidator
{
    use AssertValidTrait;

    private const AUTH_HTTP_HEADER_NAME = 'Authorization';
    private const USER_AGENT_HTTP_HEADER_NAME = 'User-Agent';

    /** @var DataFromAgentPlusRawExpectations */
    protected $expectations;

    /** @var DataFromAgentPlusRaw */
    protected $actual;

    private function __construct(DataFromAgentPlusRawExpectations $expectations, DataFromAgentPlusRaw $actual)
    {
        $this->expectations = $expectations;
        $this->actual = $actual;
    }

    public static function validate(DataFromAgentPlusRaw $actual, DataFromAgentPlusRawExpectations $expectations): void
    {
        (new self($expectations, $actual))->validateImpl();
    }

    private function validateImpl(): void
    {
        DataFromAgentValidator::validate($this->actual, $this->expectations);

        foreach ($this->actual->getAllIntakeApiRequests() as $intakeApiRequest) {
            $this->validateIntakeApiRequest($intakeApiRequest);
        }
    }

    private function validateIntakeApiRequest(IntakeApiRequest $intakeApiRequest): void
    {
        $appCodeHostParams
            = $this->findAppCodeHostsParamsBySpawnedProcessInternalId($intakeApiRequest->agentEphemeralId);
        $this->validateIntakeApiHttpRequestHeaders($intakeApiRequest->headers, $appCodeHostParams);
    }

    private function findAppCodeHostsParamsBySpawnedProcessInternalId(
        string $spawnedProcessInternalId
    ): AppCodeHostParams {
        foreach ($this->expectations->appCodeInvocations as $appCodeInvocation) {
            foreach ($appCodeInvocation->appCodeHostsParams as $appCodeHostParams) {
                if ($appCodeHostParams->spawnedProcessInternalId === $spawnedProcessInternalId) {
                    return $appCodeHostParams;
                }
            }
        }
        TestCase::fail(
            'AppCodeHostParams with spawnedProcessInternalId `' . $spawnedProcessInternalId . '\' not found'
        );
    }

    /**
     * @param array<string, array<string>> $headers
     * @param AppCodeHostParams            $appCodeHostParams
     */
    private function validateIntakeApiHttpRequestHeaders(
        array $headers,
        AppCodeHostParams $appCodeHostParams
    ): void {
        $this->validateAuthIntakeApiHttpRequestHeader($headers, $appCodeHostParams);
        $this->validateUserAgentIntakeApiHttpRequestHeader($headers, $appCodeHostParams);
    }

    /**
     * @param array<string, array<string>> $headers
     */
    private function validateAuthIntakeApiHttpRequestHeader(
        array $headers,
        AppCodeHostParams $appCodeHostParams
    ): void {
        $configuredApiKey = $appCodeHostParams->getExplicitlySetAgentStringOptionValue(OptionNames::API_KEY);
        $configuredSecretToken = $appCodeHostParams->getExplicitlySetAgentStringOptionValue(OptionNames::SECRET_TOKEN);
        self::verifyAuthIntakeApiHttpRequestHeader($configuredApiKey, $configuredSecretToken, $headers);
    }

    /**
     * @param ?string                      $configuredApiKey
     * @param ?string                      $configuredSecretToken
     * @param array<string, array<string>> $headers
     */
    public static function verifyAuthIntakeApiHttpRequestHeader(
        ?string $configuredApiKey,
        ?string $configuredSecretToken,
        array $headers
    ): void {
        $expectedAuthHeaderValue = $configuredApiKey === null
            ? ($configuredSecretToken === null ? null : "Bearer $configuredSecretToken")
            : "ApiKey $configuredApiKey";

        if ($expectedAuthHeaderValue === null) {
            TestCase::assertArrayNotHasKey(self::AUTH_HTTP_HEADER_NAME, $headers);
        } else {
            $actualAuthHeaderValue = $headers[self::AUTH_HTTP_HEADER_NAME];
            TestCase::assertCount(1, $actualAuthHeaderValue);
            TestCase::assertSame($expectedAuthHeaderValue, $actualAuthHeaderValue[0]);
        }
    }

    /**
     * @param array<string, array<string>> $headers
     * @param AppCodeHostParams            $appCodeHostParams
     */
    private function validateUserAgentIntakeApiHttpRequestHeader(
        array $headers,
        AppCodeHostParams $appCodeHostParams
    ): void {
        $configuredServiceName = $appCodeHostParams->getExplicitlySetAgentStringOptionValue(OptionNames::SERVICE_NAME);
        $configuredServiceVersion
            = $appCodeHostParams->getExplicitlySetAgentStringOptionValue(OptionNames::SERVICE_VERSION);
        $expectedUserAgentHttpRequestHeaderValue = EventSender::buildUserAgentHttpHeader(
            MetadataValidator::deriveExpectedServiceName($configuredServiceName),
            Tracer::limitNullableKeywordString($configuredServiceVersion)
        );
        self::verifyUserAgentIntakeApiHttpRequestHeader($expectedUserAgentHttpRequestHeaderValue, $headers);
    }

    /**
     * @param array<string, array<string>> $headers
     */
    public static function verifyUserAgentIntakeApiHttpRequestHeader(
        string $expectedHeaderValue,
        array $headers
    ): void {
        $actualHeaderValue = $headers[self::USER_AGENT_HTTP_HEADER_NAME];
        TestCase::assertCount(1, $actualHeaderValue);
        TestCase::assertSame($expectedHeaderValue, $actualHeaderValue[0]);
    }
}
