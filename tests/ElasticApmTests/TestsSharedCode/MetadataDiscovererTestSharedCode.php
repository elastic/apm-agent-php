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

namespace ElasticApmTests\TestsSharedCode;

use Elastic\Apm\Impl\Config\OptionNames;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataFromAgent;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\MetadataValidator;
use ElasticApmTests\Util\MixedMap;
use ElasticApmTests\Util\TestCaseBase;

final class MetadataDiscovererTestSharedCode
{
    public const EXPECTED_LABELS_KEY = 'expected_labels';

    /**
     * @param ?callable $adaptDataSets
     *
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestGlobalLabels(?callable $adaptDataSets = null): iterable
    {
        /**
         * @return iterable<array<string, mixed>>
         */
        $genDataSets = function (): iterable {
            // Default value
            yield [OptionNames::GLOBAL_LABELS => null, self::EXPECTED_LABELS_KEY => null];

            //
            // Invalid values
            //

            yield [OptionNames::GLOBAL_LABELS => 'key_without_separator', self::EXPECTED_LABELS_KEY => null];

            // Invalid value
            yield [OptionNames::GLOBAL_LABELS => 'duplicate_key=val1,duplicate_key=val2', self::EXPECTED_LABELS_KEY => null];

            //
            // Valid values
            //

            $optRawValue = "key1 \n= \t  value1 ";
            $expectedParsedValue = ['key1' => 'value1'];
            $optRawValue .= ',2 =3  ';
            $expectedParsedValue[2] = 3;
            $optRawValue .= ",key3 \t =\n 34.5";
            $expectedParsedValue['key3'] = 34.5;
            $optRawValue .= ',key4 = true';
            $expectedParsedValue['key4'] = true;
            $maxLengthKeywordString = TestCaseBase::generateMaxLengthKeywordString();
            $optRawValue .= ', key5 = ' . $maxLengthKeywordString . '+';
            $expectedParsedValue['key5'] = $maxLengthKeywordString;
            $optRawValue .= ', ' . $maxLengthKeywordString . '+' . ' = value with max length keyword key  ';
            $expectedParsedValue[$maxLengthKeywordString] = 'value with max length keyword key';
            $optRawValue .= ', = value_for_empty_key';
            $expectedParsedValue[''] = 'value_for_empty_key';
            $optRawValue .= ", \n key_with_empty_value =";
            $expectedParsedValue['key_with_empty_value'] = '';
            yield [OptionNames::GLOBAL_LABELS => $optRawValue, self::EXPECTED_LABELS_KEY => $expectedParsedValue];

            // Numeric string key
            yield [OptionNames::GLOBAL_LABELS => '0=value_0', self::EXPECTED_LABELS_KEY => ['0' => 'value_0']];
        };

        return DataProviderForTestBuilder::convertEachDataSetToMixedMapAndAddDesc(
            /**
             * @return iterable<array<string, mixed>>
             */
            function () use ($genDataSets, $adaptDataSets): iterable {
                $dataSets = $genDataSets();
                return $adaptDataSets === null ? $dataSets : $adaptDataSets($dataSets);
            }
        );
    }

    /**
     * @param ?array<string|bool|int|float|null> $expectedLabels
     * @param DataFromAgent                      $dataFromAgent
     *
     * @return void
     */
    public static function implTestGlobalLabelsAssertPart(?array $expectedLabels, DataFromAgent $dataFromAgent): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        TestCaseBase::assertCountableNotEmpty($dataFromAgent->metadatas);

        $dbgCtx->pushSubScope();
        foreach ($dataFromAgent->metadatas as $metadata) {
            $dbgCtx->add(['metadata' => $metadata]);
            MetadataValidator::assertLabels($expectedLabels, $metadata->labels);
        }
        $dbgCtx->popSubScope();
    }
}
