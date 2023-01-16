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

namespace ElasticApmTests\ComponentTests;

use Elastic\Apm\Impl\Config\AllOptionsMetadata;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\WildcardListMatcher;
use ElasticApmTests\ComponentTests\Util\AgentConfigSourceKind;
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\HttpAppCodeRequestParams;
use ElasticApmTests\Util\TransactionExpectations;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class ConfigSettingTest extends ComponentTestCaseBase
{
    private const APP_CODE_ARGS_KEY_OPTION_NAME = 'APP_CODE_ARGS_KEY_OPTION_NAME';
    private const APP_CODE_ARGS_KEY_OPTION_EXPECTED_VALUE = 'APP_CODE_ARGS_KEY_OPTION_EXPECTED_VALUE';

    private const APP_CODE_RESPONSE_HTTP_STATUS_CODE = 234;

    /**
     * @return array<string, array<string|int, mixed>>
     */
    private static function buildOptionNameToRawToValue(): array
    {
        /**
         * @param string[] $rawValues
         *
         * @return array<string|int, mixed>
         */
        $stringRawToParsedValues = function (array $rawValues): array {
            $rawToParsedValues = [];
            foreach ($rawValues as $rawVal) {
                $rawToParsedValues[$rawVal] = trim($rawVal);
            }
            return $rawToParsedValues;
        };

        /**
         * @param ?bool $valueToExclude
         *
         * @return array<string, ?bool>
         */
        $boolRawToParsedValues = function (?bool $valueToExclude = null): array {
            /** @var array<string, ?bool> $result */
            $result = [
                'false'         => false,
                'true'          => true,
                'invalid value' => null,
            ];

            if ($valueToExclude !== null) {
                foreach ($result as $rawVal => $expectedVal) {
                    if ($expectedVal === $valueToExclude) {
                        unset($result[$rawVal]);
                    }
                }
            }
            return $result;
        };

        $logLevelRawToParsedValues = [
            " \t CRITICAL \t\n" => LogLevel::CRITICAL,
            'not valid'         => null,
        ];

        $durationRawToParsedValues = [
            "\t\n 10s \t " => 10 * 1000.0 /* <- in milliseconds */,
            "\t  3m\n"     => 3 * 60 * 1000.0 /* <- in milliseconds */,
            'not valid'    => null,
        ];

        $intRawToParsedValues = [
            "\n\t 123 " => 123,
            'not valid' => null,
        ];

        $doubleRawToParsedValues = [
            " \t\n 0.5"        => 0.5,
            "not valid \t 0.5" => null,
        ];

        $wildcardListRawToParsedValues = [
            " /a/*, \t(?-i)/b1/ /b2 \t \n, (?-i) **c*\t * \t " => "/a/*, (?-i)/b1/ /b2, (?-i) *c*\t *",
        ];

        $asyncBackendCommValues = $boolRawToParsedValues(
            self::isMainAppCodeHostHttp() ? null : true /* <- valueToExclude */
        );

        return [
            OptionNames::API_KEY                  => $stringRawToParsedValues(['1my_api_key3', "my api \t key"]),
            OptionNames::ASYNC_BACKEND_COMM       => $asyncBackendCommValues,
            OptionNames::BREAKDOWN_METRICS        => $boolRawToParsedValues(),
            OptionNames::CAPTURE_ERRORS           => $boolRawToParsedValues(),
            OptionNames::ENABLED                  => $boolRawToParsedValues(/* valueToExclude: */ false),
            OptionNames::DEV_INTERNAL             => $wildcardListRawToParsedValues,
            OptionNames::DISABLE_INSTRUMENTATIONS => $wildcardListRawToParsedValues,
            OptionNames::DISABLE_SEND             => $boolRawToParsedValues(/* valueToExclude: */ true),
            OptionNames::ENVIRONMENT              => $stringRawToParsedValues([" my_environment \t "]),
            OptionNames::HOSTNAME                 => $stringRawToParsedValues([" \t my_hostname"]),
            OptionNames::LOG_LEVEL                => $logLevelRawToParsedValues,
            OptionNames::LOG_LEVEL_STDERR         => $logLevelRawToParsedValues,
            OptionNames::LOG_LEVEL_SYSLOG         => $logLevelRawToParsedValues,
            OptionNames::NON_KEYWORD_STRING_MAX_LENGTH
                                                  => $intRawToParsedValues,
            // TODO: Sergey Kleyman: Implement: test with PROFILING_INFERRED_SPANS_ENABLED set to true
            OptionNames::PROFILING_INFERRED_SPANS_ENABLED
                                                  => $boolRawToParsedValues(/* valueToExclude: */ true),
            OptionNames::PROFILING_INFERRED_SPANS_MIN_DURATION
                                                  => $durationRawToParsedValues,
            OptionNames::PROFILING_INFERRED_SPANS_SAMPLING_INTERVAL
                                                  => $durationRawToParsedValues,
            OptionNames::SANITIZE_FIELD_NAMES     => $wildcardListRawToParsedValues,
            OptionNames::SECRET_TOKEN             => $stringRawToParsedValues(['9my_secret_token0', "secret \t token"]),
            OptionNames::SERVER_TIMEOUT           => $durationRawToParsedValues,
            OptionNames::SERVICE_NAME             => $stringRawToParsedValues(['my service \t name']),
            OptionNames::SERVICE_NODE_NAME        => $stringRawToParsedValues([' my_service_node_name  \t ']),
            OptionNames::SERVICE_VERSION          => $stringRawToParsedValues(['my service version ! 123']),
            OptionNames::TRANSACTION_IGNORE_URLS  => $wildcardListRawToParsedValues,
            OptionNames::TRANSACTION_MAX_SPANS    => $intRawToParsedValues,
            OptionNames::TRANSACTION_SAMPLE_RATE  => $doubleRawToParsedValues,
            OptionNames::URL_GROUPS               => $wildcardListRawToParsedValues,
            OptionNames::VERIFY_SERVER_CERT       => $boolRawToParsedValues(),
        ];
    }

    public function testBuildOptionNameToRawToValueIncludesAllOptions(): void
    {
        $optNamesFromAllOptionsMetadata = array_keys(AllOptionsMetadata::get());
        self::assertTrue(sort(/* ref */ $optNamesFromAllOptionsMetadata));
        $optNamesFromBuildOptionNameToRawToParsedValue = array_keys(self::buildOptionNameToRawToValue());
        self::assertTrue(sort(/* ref */ $optNamesFromAllOptionsMetadata));
        self::assertEqualsCanonicalizing(
            $optNamesFromAllOptionsMetadata,
            $optNamesFromBuildOptionNameToRawToParsedValue
        );
    }

    /**
     * @return iterable<array{AgentConfigSourceKind, string, string, mixed}>>
     */
    public function dataProviderForTestAllWaysToSetConfig(): iterable
    {
        $optNameToRawToParsedValue = self::buildOptionNameToRawToValue();

        if (self::isSmoke()) {
            $optNameToRawToParsedValueForSmoke = [];
            foreach ($optNameToRawToParsedValue as $optName => $optRawToExpectedParsedValues) {
                $optNameToRawToParsedValueForSmoke[$optName] = self::adaptToSmoke($optRawToExpectedParsedValues);
            }
            $optNameToRawToParsedValue = $optNameToRawToParsedValueForSmoke;
        }

        $agentConfigSourceKindIndex = 0;
        /**
         * @return AgentConfigSourceKind[]
         */
        $agentConfigSourceKindVariants = function () use (&$agentConfigSourceKindIndex): array {
            if (!self::isSmoke()) {
                return AgentConfigSourceKind::all();
            }

            $result = [AgentConfigSourceKind::all()[$agentConfigSourceKindIndex]];
            ++$agentConfigSourceKindIndex;
            if ($agentConfigSourceKindIndex === count(AgentConfigSourceKind::all())) {
                $agentConfigSourceKindIndex = 0;
            }
            return $result;
        };

        foreach ($optNameToRawToParsedValue as $optName => $optRawToExpectedParsedValues) {
            foreach ($optRawToExpectedParsedValues as $optRawVal => $optExpectedVal) {
                foreach ($agentConfigSourceKindVariants() as $agentConfigSourceKind) {
                    if ($agentConfigSourceKind === AgentConfigSourceKind::iniFile() && is_string($optRawVal)) {
                        $optRawVal = str_replace("\n", "\t", $optRawVal);
                    }
                    $optExpectedVal = $optExpectedVal ?? AllOptionsMetadata::get()[$optName]->defaultValue();
                    yield [$agentConfigSourceKind, $optName, strval($optRawVal), $optExpectedVal];
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $appCodeArgs
     */
    public static function appCodeForTestAllWaysToSetConfig(array $appCodeArgs): void
    {
        $optName = self::getMandatoryAppCodeArg($appCodeArgs, self::APP_CODE_ARGS_KEY_OPTION_NAME);
        TestCase::assertIsString($optName);
        /** @var string $optName */
        $optExpectedVal = self::getMandatoryAppCodeArg($appCodeArgs, self::APP_CODE_ARGS_KEY_OPTION_EXPECTED_VALUE);

        $tracer = self::getTracerFromAppCode();

        $optActualVal = $tracer->getConfig()->parsedValueFor($optName);

        if ($optActualVal instanceof WildcardListMatcher) {
            $areValuesEqual = (strval($optActualVal) === $optExpectedVal);
        } else {
            $areValuesEqual = ($optActualVal == $optExpectedVal);
        }

        if (!$areValuesEqual) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    'Expected option parsed value is not equal to the actual parsed value',
                    [
                        'optName'             => $optName,
                        'optExpectedVal'      => $optExpectedVal,
                        'optExpectedVal type' => DbgUtil::getType($optExpectedVal),
                        'optActualVal'        => $optActualVal,
                        'optActualVal type'   => DbgUtil::getType($optActualVal),
                    ]
                )
            );
        }

        http_response_code(self::APP_CODE_RESPONSE_HTTP_STATUS_CODE);
    }

    /**
     * @dataProvider dataProviderForTestAllWaysToSetConfig
     *
     * @param AgentConfigSourceKind $agentConfigSourceKind
     * @param string                $optName
     * @param string                $optRawVal
     * @param mixed                 $optExpectedVal
     */
    public function testAllWaysToSetConfig(
        AgentConfigSourceKind $agentConfigSourceKind,
        string $optName,
        string $optRawVal,
        $optExpectedVal
    ): void {
        TransactionExpectations::$defaultIsSampled = null;
        TransactionExpectations::$defaultDroppedSpansCount = null;
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($agentConfigSourceKind, $optName, $optRawVal): void {
                $appCodeParams->setDefaultAgentConfigSource($agentConfigSourceKind);
                $appCodeParams->setAgentOption($optName, $optRawVal);
            }
        );
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestAllWaysToSetConfig']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($optName, $optExpectedVal): void {
                $appCodeRequestParams->setAppCodeArgs(
                    [
                        self::APP_CODE_ARGS_KEY_OPTION_NAME           => $optName,
                        self::APP_CODE_ARGS_KEY_OPTION_EXPECTED_VALUE => $optExpectedVal,
                    ]
                );
                if ($appCodeRequestParams instanceof HttpAppCodeRequestParams) {
                    $appCodeRequestParams->expectedHttpResponseStatusCode = self::APP_CODE_RESPONSE_HTTP_STATUS_CODE;
                }
            }
        );
        $this->waitForOneEmptyTransaction($testCaseHandle);
    }
}
