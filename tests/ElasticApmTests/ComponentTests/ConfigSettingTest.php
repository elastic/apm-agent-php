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
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\WildcardListMatcher;
use ElasticApmTests\ComponentTests\Util\AgentConfigSetter;
use ElasticApmTests\ComponentTests\Util\AgentConfigSetterIni;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgent;
use ElasticApmTests\ComponentTests\Util\TestProperties;
use RuntimeException;

final class ConfigSettingTest extends ComponentTestCaseBase
{
    private const APP_CODE_ARGS_KEY_OPTION_NAME = 'APP_CODE_ARGS_KEY_OPTION_NAME';
    private const APP_CODE_ARGS_KEY_OPTION_EXPECTED_VALUE = 'APP_CODE_ARGS_KEY_OPTION_EXPECTED_VALUE';

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
            /** @var array<string, ?bool> */
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

        return [
            OptionNames::API_KEY                  => $stringRawToParsedValues(['my_api_key', "\t\n  my api key "]),
            OptionNames::ASYNC_BACKEND_COMM       => $boolRawToParsedValues(),
            OptionNames::BREAKDOWN_METRICS        => $boolRawToParsedValues(),
            OptionNames::ENABLED                  => $boolRawToParsedValues(/* valueToExclude: */ false),
            OptionNames::DEV_INTERNAL             => $wildcardListRawToParsedValues,
            OptionNames::DISABLE_INSTRUMENTATIONS => $wildcardListRawToParsedValues,
            OptionNames::DISABLE_SEND             => $boolRawToParsedValues(/* valueToExclude: */ true),
            OptionNames::ENVIRONMENT              => $stringRawToParsedValues([" my_environment \t "]),
            OptionNames::HOSTNAME                 => $stringRawToParsedValues([" \t my_hostname"]),
            OptionNames::LOG_LEVEL                => $logLevelRawToParsedValues,
            OptionNames::LOG_LEVEL_STDERR         => $logLevelRawToParsedValues,
            OptionNames::LOG_LEVEL_SYSLOG         => $logLevelRawToParsedValues,
            OptionNames::SECRET_TOKEN             => $stringRawToParsedValues([" my_secret_token \t"]),
            OptionNames::SERVER_TIMEOUT           => $durationRawToParsedValues,
            OptionNames::SERVICE_NAME             => $stringRawToParsedValues([' \t my_service_name \t']),
            OptionNames::SERVICE_NODE_NAME        => $stringRawToParsedValues([' my_service_node_name  \t ']),
            OptionNames::SERVICE_VERSION          => $stringRawToParsedValues([" my_service_version"]),
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
     * @return iterable<array{AgentConfigSetter, string, string, mixed}>>
     */
    public function dataProviderForTestAllWaysToSetConfig(): iterable
    {
        $optNameToRawToParsedValue = self::buildOptionNameToRawToValue();

        foreach ($this->allConfigSetters as $configSetter) {
            foreach ($optNameToRawToParsedValue as $optName => $optRawToValue) {
                foreach ($optRawToValue as $optRawVal => $optExpectedVal) {
                    if ($configSetter instanceof AgentConfigSetterIni) {
                        $optRawToValue = str_replace("\n", "\t", $optRawToValue);
                    }
                    $optExpectedVal = $optExpectedVal ?? AllOptionsMetadata::get()[$optName]->defaultValue();
                    yield [$configSetter, $optName, strval($optRawVal), $optExpectedVal];
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
        $optExpectedVal = self::getMandatoryAppCodeArg($appCodeArgs, self::APP_CODE_ARGS_KEY_OPTION_EXPECTED_VALUE);

        $tracer = GlobalTracerHolder::get();
        if (!($tracer instanceof Tracer)) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage('$tracer is not an instance of Tracer class', ['$tracer' => $tracer])
            );
        }

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

        http_response_code(234);
    }

    /**
     * @dataProvider dataProviderForTestAllWaysToSetConfig
     *
     * @param AgentConfigSetter $configSetter
     * @param string            $optName
     * @param string            $optRawVal
     * @param mixed             $optExpectedVal
     */
    public function testAllWaysToSetConfig(
        AgentConfigSetter $configSetter,
        string $optName,
        string $optRawVal,
        $optExpectedVal
    ): void {
        $testProperties = (new TestProperties())
            ->withRoutedAppCode([__CLASS__, 'appCodeForTestAllWaysToSetConfig'])
            ->withAppCodeArgs(
                [
                    self::APP_CODE_ARGS_KEY_OPTION_NAME           => $optName,
                    self::APP_CODE_ARGS_KEY_OPTION_EXPECTED_VALUE => $optExpectedVal,
                ]
            )
            ->withExpectedStatusCode(234);
        $configSetter->set($optName, $optRawVal);
        $testProperties->withAgentConfig($configSetter);

        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            $testProperties,
            function (DataFromAgent $dataFromAgent): void {
                $this->verifyTransactionWithoutSpans($dataFromAgent);
            }
        );
    }
}
