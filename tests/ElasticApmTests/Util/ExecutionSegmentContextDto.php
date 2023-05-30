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

namespace ElasticApmTests\Util;

use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\ExecutionSegmentContext;

abstract class ExecutionSegmentContextDto
{
    use AssertValidTrait;

    /** @var ?array<string, string|bool|int|float|null> */
    public $labels = null;

    /**
     * @param mixed $actual
     *
     * @phpstan-assert array<string, string|bool|int|float|null> $actual
     */
    private static function assertValidKeyValueMap($actual, bool $shouldKeyValueStringsBeKeyword): void
    {
        $maxLength = $shouldKeyValueStringsBeKeyword ? Constants::KEYWORD_STRING_MAX_LENGTH : null;
        TestCaseBase::assertIsArray($actual);
        foreach ($actual as $key => $value) {
            self::assertValidString($key, /* isNullable: */ false, $maxLength);
            TestCaseBase::assertTrue(ExecutionSegmentContext::doesValueHaveSupportedLabelType($value));
            if (is_string($value)) {
                self::assertValidString($value, /* isNullable: */ false, $maxLength);
            }
        }
    }

    /**
     * @param Optional<?array<string, string|bool|int|float|null>> $expected
     * @param mixed                                                $actual
     * @param bool                                                 $shouldKeyValueStringsBeKeyword
     *
     * @phpstan-assert ?array<string, string|bool|int|float|null> $actual
     */
    protected static function assertKeyValueMapsMatch(Optional $expected, $actual, bool $shouldKeyValueStringsBeKeyword): void
    {
        if ($actual === null) {
            TestCaseBase::assertNull($expected->getValueOr(null));
            return;
        }

        self::assertValidKeyValueMap($actual, $shouldKeyValueStringsBeKeyword);
        /** @var array<string, string|bool|int|float|null> $actual */

        if (!$expected->isValueSet()) {
            return;
        }

        $expectedArray = $expected->getValue();
        TestCaseBase::assertNotNull($expectedArray);
        /** @var array<string, string|bool|int|float|null> $expectedArray */
        TestCaseBase::assertSameCount($expectedArray, $actual);
        foreach ($expectedArray as $expectedKey => $expectedValue) {
            TestCaseBase::assertSameValueInArray($expectedKey, $expectedValue, $actual);
        }
    }

    /**
     * @param Optional<?array<string, string|bool|int|float|null>> $expected
     * @param mixed                                                $actual
     *
     * @phpstan-assert ?array<string, string|bool|int|float|null> $actual
     */
    private static function assertLabelsMatch(Optional $expected, $actual): void
    {
        self::assertKeyValueMapsMatch($expected, $actual, /* shouldKeyValueStringsBeKeyword */ true);
    }

    /**
     * @param mixed $actual
     *
     * @return ?array<string, string|bool|int|float|null>
     */
    private static function assertValidLabels($actual): ?array
    {
        /**
         * @var Optional<?array<string, string|bool|int|float|null>> $expected
         * @noinspection PhpRedundantVariableDocTypeInspection
         */
        $expected = new Optional();
        self::assertLabelsMatch($expected, $actual);
        return $actual;
    }

    /**
     * @param mixed $key
     * @param mixed $value
     * @param self  $result
     *
     * @return bool
     */
    public static function deserializeKeyValue($key, $value, self $result): bool
    {
        switch ($key) {
            case 'tags':
                $result->labels = self::assertValidLabels($value);
                return true;
            default:
                return false;
        }
    }

    protected function assertMatchesExecutionSegment(ExecutionSegmentContextExpectations $expectations): void
    {
        self::assertLabelsMatch($expectations->labels, $this->labels);
    }
}
