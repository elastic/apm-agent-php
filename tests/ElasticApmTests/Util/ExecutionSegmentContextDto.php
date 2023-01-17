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
use PHPUnit\Framework\TestCase;

abstract class ExecutionSegmentContextDto
{
    use AssertValidTrait;

    /** @var ?array<string, string|bool|int|float|null> */
    public $labels = null;

    /**
     * @param mixed $map
     *
     * @return array<string, string|bool|int|float|null>
     */
    protected static function assertValidKeyValueMap($map, bool $shouldBeKeywordString): array
    {
        $maxLength = $shouldBeKeywordString ? Constants::KEYWORD_STRING_MAX_LENGTH : null;
        TestCase::assertIsArray($map);
        foreach ($map as $key => $value) {
            self::assertValidString($key, /* isNullable: */ false, $maxLength);
            TestCase::assertTrue(ExecutionSegmentContext::doesValueHaveSupportedLabelType($value));
            if (is_string($value)) {
                self::assertValidString($value, /* isNullable: */ false, $maxLength);
            }
        }
        /** @var array<string, string|bool|int|float|null> $map */
        return $map;
    }

    /**
     * @param mixed $labels
     *
     * @return array<string, string|bool|int|float|null>
     */
    private static function assertValidLabels($labels): array
    {
        return self::assertValidKeyValueMap($labels, /* shouldBeKeywordString */ true);
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

    /**
     * @return void
     */
    public function assertValid(): void
    {
        if ($this->labels !== null) {
            self::assertValidLabels($this->labels);
        }
    }
}
