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

use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\WildcardListMatcher;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgent;
use ElasticApmTests\ComponentTests\Util\TestProperties;

final class DynamicConfigSettingTest extends ComponentTestCaseBase
{
    private const APP_CODE_ARGS_KEY_OPTION_NAME = 'APP_CODE_ARGS_KEY_OPTION_NAME';
    private const APP_CODE_ARGS_KEY_OPTION_EXPECTED_VALUE = 'APP_CODE_ARGS_KEY_OPTION_EXPECTED_VALUE';

    /**
     * @return array<array{string, array<array{string, mixed}>}>
     */
    public static function dataProviderForTestDynamicConfigSetting(): array
    {
        /**
         * @param int $logLevel
         *
         * @return array{string, int}
         */
        $logLevelToRawParsedPair = function (int $logLevel): array {
            return [LogLevel::intToName($logLevel), $logLevel];
        };

        $logLevelValues = [
            $logLevelToRawParsedPair(LogLevel::TRACE),
            $logLevelToRawParsedPair(LogLevel::OFF),
            $logLevelToRawParsedPair(LogLevel::CRITICAL),
            $logLevelToRawParsedPair(LogLevel::DEBUG),
            $logLevelToRawParsedPair(LogLevel::WARNING),
        ];

        return [
            [OptionNames::LOG_LEVEL, $logLevelValues],
        ];
    }

    public static function appCodeForTestNumberOfDynamicConfigOptions(): void
    {
        /**
         * elastic_apm_* functions are provided by the elastic_apm extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         * @phpstan-ignore-next-line
         */
        $cPartNumberOfDynamicConfigOptions = \elastic_apm_get_number_of_dynamic_config_options();

        self::appAssertSame($cPartNumberOfDynamicConfigOptions, count(self::dataProviderForTestDynamicConfigSetting()));

        http_response_code(234);
    }

    public function testNumberOfDynamicConfigOptions(): void
    {
        $testProperties = (new TestProperties())
            ->withRoutedAppCode([__CLASS__, 'appCodeForTestNumberOfDynamicConfigOptions'])
            ->withExpectedStatusCode(234);

        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            $testProperties,
            function (DataFromAgent $dataFromAgent): void {
                $this->verifyTransactionWithoutSpans($dataFromAgent);
            }
        );
    }

    /**
     * @param array<string, mixed> $appCodeArgs
     */
    public static function appCodeForTestDynamicConfigSetting(array $appCodeArgs): void
    {
        $optName = self::getMandatoryAppCodeArg($appCodeArgs, self::APP_CODE_ARGS_KEY_OPTION_NAME);
        $optExpectedVal = self::getMandatoryAppCodeArg($appCodeArgs, self::APP_CODE_ARGS_KEY_OPTION_EXPECTED_VALUE);

        $tracer = GlobalTracerHolder::get();
        self::appAssertTrue(
            $tracer instanceof Tracer,
            '$tracer is not an instance of Tracer class',
            ['$tracer' => $tracer]
        );
        /** @var Tracer $tracer */

        $optActualVal = $tracer->getConfig()->parsedValueFor($optName);

        if ($optActualVal instanceof WildcardListMatcher) {
            $areValuesEqual = (strval($optActualVal) === $optExpectedVal);
        } else {
            $areValuesEqual = ($optActualVal == $optExpectedVal);
        }

        self::appAssertTrue(
            $areValuesEqual,
            'Expected option parsed value is not equal to the actual parsed value',
            [
                'optName'             => $optName,
                'optExpectedVal'      => $optExpectedVal,
                'optExpectedVal type' => DbgUtil::getType($optExpectedVal),
                'optActualVal'        => $optActualVal,
                'optActualVal type'   => DbgUtil::getType($optActualVal),
            ]
        );

        http_response_code(234);
    }

    /**
     * @dataProvider dataProviderForTestDynamicConfigSetting
     *
     * @param string       $optName
     * @param array<mixed> $optValues
     */
    public function testDynamicConfigSetting(string $optName, array $optValues): void
    {
        $optRawParsedPair = $optValues[0];
        $testProperties = (new TestProperties())
            ->withRoutedAppCode([__CLASS__, 'appCodeForTestDynamicConfigSetting'])
            ->withAppCodeArgs(
                [
                    self::APP_CODE_ARGS_KEY_OPTION_NAME           => $optName,
                    self::APP_CODE_ARGS_KEY_OPTION_EXPECTED_VALUE => $optRawParsedPair[1],
                ]
            )
            ->withExpectedStatusCode(234);
        $configSetter = $this->randomConfigSetter();
        $configSetter->set($optName, $optRawParsedPair[0]);
        $testProperties->withAgentConfig($configSetter);

        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            $testProperties,
            function (DataFromAgent $dataFromAgent): void {
                $this->verifyTransactionWithoutSpans($dataFromAgent);
            }
        );
    }
}
