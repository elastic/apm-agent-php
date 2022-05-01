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
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\WildcardListMatcher;
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\HttpAppCodeRequestParams;
use PHPUnit\Framework\TestCase;

final class DynamicConfigSettingTest extends ComponentTestCaseBase
{
    private const APP_CODE_ARGS_KEY_OPTION_NAME = 'APP_CODE_ARGS_KEY_OPTION_NAME';
    private const APP_CODE_ARGS_KEY_OPTION_EXPECTED_VALUE = 'APP_CODE_ARGS_KEY_OPTION_EXPECTED_VALUE';

    private const APP_CODE_RESPONSE_HTTP_STATUS_CODE = 234;

    /**
     * @return array<string, array<array{string, mixed}>>
     */
    private static function buildDynamicOptionsDataSet(): array
    {
        /**
         * @param int $logLevel
         *
         * @return array{string, int}
         */
        $logLevelToRawParsedPair = function (int $logLevel): array {
            return [LogLevel::intToName($logLevel), $logLevel];
        };

        /** @var array<array{string, mixed}> */
        $logLevelValues = [
            $logLevelToRawParsedPair(LogLevel::TRACE),
            $logLevelToRawParsedPair(LogLevel::OFF),
            $logLevelToRawParsedPair(LogLevel::CRITICAL),
            $logLevelToRawParsedPair(LogLevel::DEBUG),
            $logLevelToRawParsedPair(LogLevel::WARNING),
        ];

        return [
            OptionNames::LOG_LEVEL => $logLevelValues,
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

        TestCase::assertSame($cPartNumberOfDynamicConfigOptions, count(self::buildDynamicOptionsDataSet()));

        http_response_code(234);
    }

    public function testNumberOfDynamicConfigOptions(): void
    {
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost();
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestNumberOfDynamicConfigOptions']),
            function (AppCodeRequestParams $appCodeRequestParams): void {
                if ($appCodeRequestParams instanceof HttpAppCodeRequestParams) {
                    $appCodeRequestParams->expectedHttpResponseStatusCode = self::APP_CODE_RESPONSE_HTTP_STATUS_CODE;
                }
            }
        );
        $this->waitForOneEmptyTransaction($testCaseHandle);
    }

    /**
     * @param array<string, mixed> $appCodeArgs
     */
    public static function appCodeForTestDynamicConfigSetting(array $appCodeArgs): void
    {
        $optName = self::getMandatoryAppCodeArg($appCodeArgs, self::APP_CODE_ARGS_KEY_OPTION_NAME);
        TestCase::assertIsString($optName);
        /** @var string $optName */
        $optExpectedVal = self::getMandatoryAppCodeArg($appCodeArgs, self::APP_CODE_ARGS_KEY_OPTION_EXPECTED_VALUE);

        $tracer = GlobalTracerHolder::get();
        TestCase::assertInstanceOf(Tracer::class, $tracer);
        /** @var Tracer $tracer */

        $optActualVal = $tracer->getConfig()->parsedValueFor($optName);

        if ($optActualVal instanceof WildcardListMatcher) {
            $areValuesEqual = (strval($optActualVal) === $optExpectedVal);
        } else {
            $areValuesEqual = ($optActualVal == $optExpectedVal);
        }

        TestCase::assertTrue(
            $areValuesEqual,
            'Expected option parsed value is not equal to the actual parsed value'
            . '; ' . LoggableToString::convert(
                [
                    'optName'             => $optName,
                    'optExpectedVal'      => $optExpectedVal,
                    'optExpectedVal type' => DbgUtil::getType($optExpectedVal),
                    'optActualVal'        => $optActualVal,
                    'optActualVal type'   => DbgUtil::getType($optActualVal),
                ]
            )
        );

        http_response_code(self::APP_CODE_RESPONSE_HTTP_STATUS_CODE);
    }

    /**
     * @return iterable<array{string, array<array{string, mixed}>}>
     */
    public function dataProviderForTestDynamicConfigSetting(): iterable
    {
        if (!self::isMainAppCodeHostHttp()) {
            yield ["", []];
            return;
        }

        foreach (self::buildDynamicOptionsDataSet() as $optName => $optValuePairs) {
            yield [$optName, $optValuePairs];
        }
    }

    /**
     * @dataProvider dataProviderForTestDynamicConfigSetting
     *
     * @param string                      $optName
     * @param array<array{string, mixed}> $optValuePairs
     */
    public function testDynamicConfigSetting(string $optName, array $optValuePairs): void
    {
        if (self::skipIfMainAppCodeHostIsNotHttp()) {
            return;
        }

        $optRawValue = $optValuePairs[0][0];
        $optParsedValue = $optValuePairs[0][1];
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($optName, $optRawValue): void {
                $appCodeParams->setAgentOption($optName, $optRawValue);
            }
        );
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestDynamicConfigSetting']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($optName, $optParsedValue): void {
                $appCodeRequestParams->setAppCodeArgs(
                    [
                        self::APP_CODE_ARGS_KEY_OPTION_NAME           => $optName,
                        self::APP_CODE_ARGS_KEY_OPTION_EXPECTED_VALUE => $optParsedValue,
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
