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
use Elastic\Apm\Impl\Config\NullableOptionMetadata;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\ComponentTests\Util\AgentConfigSourceKind;
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\HttpAppCodeRequestParams;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\MetadataExpectations;
use ElasticApmTests\Util\MixedMap;
use ElasticApmTests\Util\TextUtilForTests;
use ElasticApmTests\Util\TransactionExpectations;
use Stringable;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class ConfigSettingTest extends ComponentTestCaseBase
{
    private const APP_CODE_RESPONSE_HTTP_STATUS_CODE = 234;

    private const AGENT_CONFIG_SOURCE_KIND_KEY = 'agent_config_source_kind';
    private const OPTION_NAME_KEY = 'option_name';
    private const OPTION_RAW_VALUE_KEY = 'option_raw_value';
    private const OPTION_EXPECTED_PARSED_VALUE_KEY = 'option_expected_parsed_value';

    private const OPTIONS_PARSED_BY_NATIVE = [
        OptionNames::AST_PROCESS_ENABLED,
        OptionNames::AST_PROCESS_DEBUG_DUMP_CONVERTED_BACK_TO_SOURCE,
        OptionNames::ASYNC_BACKEND_COMM,
        OptionNames::BREAKDOWN_METRICS,
        OptionNames::CAPTURE_ERRORS,
        OptionNames::CAPTURE_ERRORS_WITH_PHP_PART,
        OptionNames::CAPTURE_EXCEPTIONS,
        OptionNames::DEV_INTERNAL_CAPTURE_ERRORS_ONLY_TO_LOG,
        OptionNames::DISABLE_SEND,
        OptionNames::ENABLED,
        OptionNames::LOG_LEVEL,
        OptionNames::LOG_LEVEL_STDERR,
        OptionNames::LOG_LEVEL_SYSLOG,
        OptionNames::PROFILING_INFERRED_SPANS_ENABLED,
        OptionNames::SERVER_TIMEOUT,
        OptionNames::SPAN_COMPRESSION_ENABLED,
        OptionNames::VERIFY_SERVER_CERT,
    ];

    private static function isOptionLogLevelRelated(string $optName): bool
    {
        return TextUtil::contains($optName, 'log_level');
    }

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

        $keyValuePairsRawToParsedValues = [
            'dept=engineering,rack=number8'                    => ['dept' => 'engineering', 'rack' => 'number8'],
            " \t key1 = \t value1 \t, \t  key2 \n=  value2 \t" => ['key1' => 'value1', 'key2' => 'value2'],
            " 0 = \t 0 \t, \t  1 \n=  123.5 \t"                => [0 => 0, 1 => 123.5],
        ];

        return [
            OptionNames::API_KEY                        => $stringRawToParsedValues(['1my_api_key3', "my api \t key"]),
            OptionNames::AST_PROCESS_ENABLED            => $boolRawToParsedValues(),
            OptionNames::AST_PROCESS_DEBUG_DUMP_CONVERTED_BACK_TO_SOURCE
                                                        => $boolRawToParsedValues(),
            OptionNames::AST_PROCESS_DEBUG_DUMP_FOR_PATH_PREFIX
                                                        => $stringRawToParsedValues(['/', '/myDir']),
            OptionNames::AST_PROCESS_DEBUG_DUMP_OUT_DIR => $stringRawToParsedValues(['/', '/myDir']),
            OptionNames::ASYNC_BACKEND_COMM             => $boolRawToParsedValues(),
            OptionNames::BREAKDOWN_METRICS              => $boolRawToParsedValues(),
            OptionNames::CAPTURE_ERRORS                 => $boolRawToParsedValues(),
            OptionNames::CAPTURE_ERRORS_WITH_PHP_PART   => $boolRawToParsedValues(),
            OptionNames::CAPTURE_EXCEPTIONS             => $boolRawToParsedValues(),
            OptionNames::DEV_INTERNAL                   => $wildcardListRawToParsedValues,
            OptionNames::DEV_INTERNAL_CAPTURE_ERRORS_ONLY_TO_LOG
                                                        => $boolRawToParsedValues(),
            OptionNames::DISABLE_INSTRUMENTATIONS       => $wildcardListRawToParsedValues,
            OptionNames::DISABLE_SEND                   => $boolRawToParsedValues(/* valueToExclude: */ true),
            OptionNames::ENABLED                        => $boolRawToParsedValues(/* valueToExclude: */ false),
            OptionNames::ENVIRONMENT                    => $stringRawToParsedValues([" my_environment \t "]),
            OptionNames::GLOBAL_LABELS                  => $keyValuePairsRawToParsedValues,
            OptionNames::HOSTNAME                       => $stringRawToParsedValues([" \t my_hostname"]),
            OptionNames::LOG_LEVEL                      => $logLevelRawToParsedValues,
            OptionNames::LOG_LEVEL_STDERR               => $logLevelRawToParsedValues,
            OptionNames::LOG_LEVEL_SYSLOG               => $logLevelRawToParsedValues,
            OptionNames::NON_KEYWORD_STRING_MAX_LENGTH  => $intRawToParsedValues,
            OptionNames::PROFILING_INFERRED_SPANS_ENABLED
                                                        => $boolRawToParsedValues(/* valueToExclude: */ true),
            OptionNames::PROFILING_INFERRED_SPANS_MIN_DURATION
                                                        => $durationRawToParsedValues,
            OptionNames::PROFILING_INFERRED_SPANS_SAMPLING_INTERVAL
                                                        => $durationRawToParsedValues,
            OptionNames::SANITIZE_FIELD_NAMES           => $wildcardListRawToParsedValues,
            OptionNames::SECRET_TOKEN                   => $stringRawToParsedValues(['9my_secret_token0', "secret \t token"]),
            OptionNames::SERVER_TIMEOUT                 => $durationRawToParsedValues,
            OptionNames::SERVICE_NAME                   => $stringRawToParsedValues(['my service \t name']),
            OptionNames::SERVICE_NODE_NAME              => $stringRawToParsedValues([' my_service_node_name  \t ']),
            OptionNames::SERVICE_VERSION                => $stringRawToParsedValues(['my service version ! 123']),
            OptionNames::SPAN_COMPRESSION_ENABLED       => $boolRawToParsedValues(),
            OptionNames::SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION
                                                        => $durationRawToParsedValues,
            OptionNames::SPAN_COMPRESSION_SAME_KIND_MAX_DURATION
                                                        => $durationRawToParsedValues,
            OptionNames::SPAN_STACK_TRACE_MIN_DURATION  => $durationRawToParsedValues,
            OptionNames::STACK_TRACE_LIMIT              => $intRawToParsedValues,
            OptionNames::TRANSACTION_IGNORE_URLS        => $wildcardListRawToParsedValues,
            OptionNames::TRANSACTION_MAX_SPANS          => $intRawToParsedValues,
            OptionNames::TRANSACTION_SAMPLE_RATE        => $doubleRawToParsedValues,
            OptionNames::URL_GROUPS                     => $wildcardListRawToParsedValues,
            OptionNames::VERIFY_SERVER_CERT             => $boolRawToParsedValues(),
        ];
    }

    public function testBuildOptionNameToRawToValueIncludesAllOptions(): void
    {
        $optNamesFromAllOptionsMetadata = array_keys(AllOptionsMetadata::get());
        self::assertTrue(sort(/* ref */ $optNamesFromAllOptionsMetadata));
        $optNamesFromBuildOptionNameToRawToParsedValue = array_keys(self::buildOptionNameToRawToValue());
        self::assertTrue(sort(/* ref */ $optNamesFromAllOptionsMetadata));
        self::assertEqualsCanonicalizing($optNamesFromAllOptionsMetadata, $optNamesFromBuildOptionNameToRawToParsedValue);
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public function dataProviderForTestAllWaysToSetConfig(): iterable
    {
        /**
         * @return iterable<array<string, mixed>>
         */
        $genDataSets = function (): iterable {
            $optNameToRawToParsedValue = self::buildOptionNameToRawToValue();

            if (self::isSmoke()) {
                $optNameToRawToParsedValue = array_map(
                    function ($optRawToExpectedParsedValues) {
                        return self::adaptToSmoke($optRawToExpectedParsedValues);
                    },
                    $optNameToRawToParsedValue
                );
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
                if (AllOptionsMetadata::get()[$optName] instanceof NullableOptionMetadata) {
                    yield [
                        self::AGENT_CONFIG_SOURCE_KIND_KEY     => null,
                        self::OPTION_NAME_KEY                  => $optName,
                        self::OPTION_RAW_VALUE_KEY             => null,
                        self::OPTION_EXPECTED_PARSED_VALUE_KEY => AllOptionsMetadata::get()[$optName]->defaultValue()
                    ];
                }
                foreach ($optRawToExpectedParsedValues as $optRawVal => $optExpectedVal) {
                    foreach ($agentConfigSourceKindVariants() as $agentConfigSourceKind) {
                        if ($agentConfigSourceKind === AgentConfigSourceKind::iniFile() && is_string($optRawVal)) {
                            $optRawVal = trim(str_replace("\n", "\t", $optRawVal));
                        }
                        yield [
                            self::AGENT_CONFIG_SOURCE_KIND_KEY     => $agentConfigSourceKind,
                            self::OPTION_NAME_KEY                  => $optName,
                            self::OPTION_RAW_VALUE_KEY             => TextUtilForTests::valuetoString($optRawVal),
                            self::OPTION_EXPECTED_PARSED_VALUE_KEY => $optExpectedVal ?? AllOptionsMetadata::get()[$optName]->defaultValue()
                        ];
                    }
                }
            }
        };

        return DataProviderForTestBuilder::convertEachDataSetToMixedMapAndAddDesc($genDataSets);
    }

    /**
     * @param mixed $optParsedValue
     *
     * @return mixed
     */
    private static function adaptParsedValueToCompare($optParsedValue)
    {
        if ($optParsedValue === null || is_scalar($optParsedValue) || is_array($optParsedValue)) {
            return $optParsedValue;
        }

        if (is_object($optParsedValue)) {
            self::assertInstanceOf(Stringable::class, $optParsedValue);
            return strval($optParsedValue);
        }

        self::fail('Unexpected $optParsedValue type: ' . DbgUtil::getType($optParsedValue));
    }

    public static function appCodeForTestAllWaysToSetConfig(MixedMap $appCodeArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $optName = $appCodeArgs->getString(self::OPTION_NAME_KEY);
        $dbgCtx->add(compact('optName'));

        /**
         * @param array<string, mixed> $varNameToValue
         */
        $addValueAndTypeToDbgCtx = function (array $varNameToValue) use ($dbgCtx): void {
            $dbgCtx->add($varNameToValue);
            $varName = ArrayUtilForTests::getSingleValue(array_keys($varNameToValue));
            $dbgCtx->add([$varName . ' type' => DbgUtil::getType($varNameToValue[$varName])]);
        };

        $optExpectedParsedValue = $appCodeArgs->get(self::OPTION_EXPECTED_PARSED_VALUE_KEY);
        // When passed from test to app code the expected parsed value might be converted from float to int if it does not have fractional part.
        // We need to convert it back to float for expected and actual value types to match.
        if (is_int($optExpectedParsedValue) && is_float(AllOptionsMetadata::get()[$optName]->defaultValue())) {
            $optExpectedParsedValue = floatval($optExpectedParsedValue);
        }
        $addValueAndTypeToDbgCtx(compact('optExpectedParsedValue'));

        $tracer = self::getTracerFromAppCode();
        $optActualValueParsedByPhpPart = $tracer->getConfig()->parsedValueFor($optName);
        $addValueAndTypeToDbgCtx(compact('optActualValueParsedByPhpPart'));
        $optActualValuesToVerify = compact('optActualValueParsedByPhpPart');

        /**
         * elastic_apm_* functions are provided by the elastic_apm extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         * @phpstan-ignore-next-line
         */
        $optActualValueParsedByNativePart = \elastic_apm_get_config_option_by_name($optName);
        // Native part uses -1 for not set log level options.
        // See logLevel_not_set in LogLevel.h
        if (self::isOptionLogLevelRelated($optName) && $optActualValueParsedByNativePart === -1) {
            $optActualValueParsedByNativePart = null;
        }
        $addValueAndTypeToDbgCtx(compact('optActualValueParsedByNativePart'));
        if (in_array($optName, self::OPTIONS_PARSED_BY_NATIVE)) {
            $optActualValuesToVerify += compact('optActualValueParsedByNativePart');
        } else {
            $optRawValue = $appCodeArgs->get(self::OPTION_RAW_VALUE_KEY);
            self::assertSame($optRawValue === null ? $optRawValue : TextUtilForTests::valuetoString($optRawValue), $optActualValueParsedByNativePart);
        }

        $dbgCtx->pushSubScope();
        foreach ($optActualValuesToVerify as $optActualValueParsedBy => $optActualParsedValue) {
            $dbgCtx->clearCurrentSubScope(compact('optActualValueParsedBy'));
            $adaptedExpectedValue = self::adaptParsedValueToCompare($optExpectedParsedValue);
            $addValueAndTypeToDbgCtx(compact('adaptedExpectedValue'));
            $adaptedActualValue = self::adaptParsedValueToCompare($optActualParsedValue);
            $addValueAndTypeToDbgCtx(compact('adaptedActualValue'));
            if (is_scalar($adaptedExpectedValue) || $adaptedExpectedValue === null) {
                self::assertSame($adaptedExpectedValue, $adaptedActualValue);
            } else {
                self::assertEquals($adaptedExpectedValue, $adaptedActualValue);
            }
        }
        $dbgCtx->popSubScope();

        http_response_code(self::APP_CODE_RESPONSE_HTTP_STATUS_CODE);
    }

    private function implTestAllWaysToSetConfig(MixedMap $testArgs): void
    {
        $agentConfigSourceKind = $testArgs->get(self::AGENT_CONFIG_SOURCE_KIND_KEY);
        if ($agentConfigSourceKind !== null) {
            self::assertInstanceOf(AgentConfigSourceKind::class, $agentConfigSourceKind);
        }
        $optName = $testArgs->getString(self::OPTION_NAME_KEY);

        TransactionExpectations::$defaultIsSampled = null;
        TransactionExpectations::$defaultDroppedSpansCount = null;
        MetadataExpectations::$labelsDefault->reset();

        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($agentConfigSourceKind, $optName, $testArgs): void {
                if ($agentConfigSourceKind !== null) {
                    $appCodeParams->setDefaultAgentConfigSource($agentConfigSourceKind);
                    $appCodeParams->setAgentOption($optName, $testArgs->getString(self::OPTION_RAW_VALUE_KEY));
                }
            }
        );
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestAllWaysToSetConfig']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($testArgs): void {
                // Remove agentConfigSourceKind because it's an object and thus cannot be sent to app code
                $appCodeArgs = $testArgs->cloneAsArray();
                unset($appCodeArgs[self::AGENT_CONFIG_SOURCE_KIND_KEY]);
                $appCodeRequestParams->setAppCodeArgs($appCodeArgs);
                if ($appCodeRequestParams instanceof HttpAppCodeRequestParams) {
                    $appCodeRequestParams->expectedHttpResponseStatusCode = self::APP_CODE_RESPONSE_HTTP_STATUS_CODE;
                }
            }
        );
        $this->waitForOneEmptyTransaction($testCaseHandle);
    }

    /**
     * @dataProvider dataProviderForTestAllWaysToSetConfig
     */
    public function testAllWaysToSetConfig(MixedMap $testArgs): void
    {
        if (self::isOptionLogLevelRelated($testArgs->getString(self::OPTION_NAME_KEY))) {
            $this->implTestAllWaysToSetConfig($testArgs);
        } else {
            self::runAndEscalateLogLevelOnFailure(
                self::buildDbgDescForTestWithArtgs(__CLASS__, __FUNCTION__, $testArgs),
                function () use ($testArgs): void {
                    $this->implTestAllWaysToSetConfig($testArgs);
                }
            );
        }
    }
}
