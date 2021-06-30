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
use ElasticApmTests\ComponentTests\Util\AgentConfigSetter;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgent;
use ElasticApmTests\ComponentTests\Util\TestProperties;
use RuntimeException;

final class ConfigSettingTest extends ComponentTestCaseBase
{
    private const APP_CODE_ARGS_KEY_OPTION_NAME = 'OPTION_NAME';
    private const APP_CODE_ARGS_KEY_OPTION_PARSED_VALUE = 'OPTION_PARSED_VALUE';

    /**
     * @return array<string, array<mixed>>
     */
    private static function buildOptionNameToRawToParsedValue(): array
    {
        $stringRawToParsedValues = function (array $rawValues) {
            $rawToParsedValues = [];
            foreach ($rawValues as $rawVal) {
                $rawToParsedValues[$rawVal] = $rawVal;
            }
            return $rawToParsedValues;
        };

        $boolRawToParsedValues = function (?bool $valueToExclude = null) {
            $rawToParsedValues = [
                'false'         => false,
                'true'          => true,
                'invalid value' => null,
            ];

            if ($valueToExclude !== null) {
                foreach ($rawToParsedValues as $rawVal => $parsedVal) {
                    if ($parsedVal === $valueToExclude) {
                        unset($rawToParsedValues[$rawVal]);
                    }
                }
            }
            return $rawToParsedValues;
        };

        $logLevelRawToParsedValues = [
            'CRITICAL'  => LogLevel::CRITICAL,
            'not valid' => null,
        ];

        $durationRawToParsedValues = [
            '10s'       => 10 * 1000.0 /* <- in milliseconds */,
            '3m'        => 3 * 60 * 1000.0 /* <- in milliseconds */,
            'not valid' => null,
        ];

        $intRawToParsedValues = [
            '123'       => 123,
            'not valid' => null,
        ];

        $doubleRawToParsedValues = [
            '0.5'       => 0.5,
            'not valid' => null,
        ];

        return [
            OptionNames::API_KEY                 => $stringRawToParsedValues(['my_api_key']),
            OptionNames::BREAKDOWN_METRICS       => $boolRawToParsedValues(),
            OptionNames::ENABLED                 => $boolRawToParsedValues(/* valueToExclude: */ false),
            OptionNames::ENVIRONMENT             => $stringRawToParsedValues(['my_environment']),
            OptionNames::HOSTNAME                => $stringRawToParsedValues(['my_hostname']),
            OptionNames::LOG_LEVEL               => $logLevelRawToParsedValues,
            OptionNames::LOG_LEVEL_STDERR        => $logLevelRawToParsedValues,
            OptionNames::LOG_LEVEL_SYSLOG        => $logLevelRawToParsedValues,
            OptionNames::SECRET_TOKEN            => $stringRawToParsedValues(['my_secret_token']),
            OptionNames::SERVER_TIMEOUT          => $durationRawToParsedValues,
            OptionNames::SERVICE_NAME            => $stringRawToParsedValues(['my_service_name']),
            OptionNames::SERVICE_VERSION         => $stringRawToParsedValues(['my_service_version']),
            OptionNames::TRANSACTION_MAX_SPANS   => $intRawToParsedValues,
            OptionNames::TRANSACTION_SAMPLE_RATE => $doubleRawToParsedValues,
            OptionNames::VERIFY_SERVER_CERT      => $boolRawToParsedValues(),
        ];
    }

    public function testOptionNameToRawToParsedValue(): void
    {
        $optNamesFromAllOptionsMetadata = array_keys(AllOptionsMetadata::get());
        self::assertTrue(sort(/* ref */ $optNamesFromAllOptionsMetadata));
        $optNamesFromBuildOptionNameToRawToParsedValue = array_keys(self::buildOptionNameToRawToParsedValue());
        self::assertTrue(sort(/* ref */ $optNamesFromAllOptionsMetadata));
        self::assertEqualsCanonicalizing(
            $optNamesFromAllOptionsMetadata,
            $optNamesFromBuildOptionNameToRawToParsedValue
        );
    }

    /**
     * @return iterable<array<int|AgentConfigSetter|mixed>>
     */
    public function dataProviderForTestAllWaysToSetConfig(): iterable
    {
        $optNameToRawToParsedValue = self::buildOptionNameToRawToParsedValue();

        foreach ($this->allConfigSetters as $configSetter) {
            foreach ($optNameToRawToParsedValue as $optName => $optRawToParsedValue) {
                foreach ($optRawToParsedValue as $optRawVal => $optParsedVal) {
                    $optParsedVal = $optParsedVal ?? AllOptionsMetadata::get()[$optName]->defaultValue();
                    yield [$configSetter, $optName, strval($optRawVal), $optParsedVal];
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
        $expectedOptParsedVal = self::getMandatoryAppCodeArg($appCodeArgs, self::APP_CODE_ARGS_KEY_OPTION_PARSED_VALUE);

        $tracer = GlobalTracerHolder::get();
        if (!$tracer instanceof Tracer) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage('$tracer is not an instance of Tracer class', ['$tracer' => $tracer])
            );
        }

        $actualOptParsedVal = $tracer->getConfig()->parsedValueFor($optName);
        if ($expectedOptParsedVal != $actualOptParsedVal) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    'Expected option parsed value is not equal to the actual parsed value',
                    [
                        'optName'                   => $optName,
                        'expectedOptParsedVal'      => $expectedOptParsedVal,
                        'expectedOptParsedVal type' => DbgUtil::getType($expectedOptParsedVal),
                        'actualOptParsedVal'        => $actualOptParsedVal,
                        'actualOptParsedVal type'   => DbgUtil::getType($actualOptParsedVal),
                    ]
                )
            );
        }
    }

    /**
     * @dataProvider dataProviderForTestAllWaysToSetConfig
     *
     * @param AgentConfigSetter $configSetter
     * @param string            $optName
     * @param string            $optRawVal
     * @param mixed             $optParsedVal
     */
    public function testAllWaysToSetConfig(
        AgentConfigSetter $configSetter,
        string $optName,
        string $optRawVal,
        $optParsedVal
    ): void {
        $testProperties = (new TestProperties())
            ->withRoutedAppCode([__CLASS__, 'appCodeForTestAllWaysToSetConfig'])
            ->withAppCodeArgs(
                [
                    self::APP_CODE_ARGS_KEY_OPTION_NAME         => $optName,
                    self::APP_CODE_ARGS_KEY_OPTION_PARSED_VALUE => $optParsedVal,
                ]
            );
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
