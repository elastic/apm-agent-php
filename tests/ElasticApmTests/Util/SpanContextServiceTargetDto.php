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

use ElasticApmTests\Util\Deserialization\DeserializationUtil;
use PHPUnit\Framework\TestCase;

final class SpanContextServiceTargetDto
{
    use AssertValidTrait;

    /** @var ?string */
    public $name;

    /** @var ?string */
    public $type;

    /**
     * @param mixed $value
     *
     * @return self
     */
    public static function deserialize($value): self
    {
        $result = new self();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'name':
                        $result->name = self::assertValidNullableKeywordString($value);
                        return true;
                    case 'type':
                        $result->type = self::assertValidNullableKeywordString($value);
                        return true;
                    default:
                        return false;
                }
            }
        );

        $result->assertValid();
        return $result;
    }

    public function assertValid(): void
    {
        $this->assertMatches(new SpanContextServiceTargetExpectations());
    }

    public function assertMatches(SpanContextServiceTargetExpectations $expectations): void
    {
        self::assertSameNullableKeywordStringExpectedOptional($expectations->name, $this->name);
        self::assertSameNullableKeywordStringExpectedOptional($expectations->type, $this->type);
    }

    public static function assertNullableMatches(
        SpanContextServiceTargetExpectations $expectations,
        ?self $actual
    ): void {
        if ($actual === null) {
            TestCase::assertTrue($expectations->isEmpty());
            return;
        }

        $actual->assertMatches($expectations);
    }
}
