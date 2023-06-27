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

final class TransactionContextDto extends ExecutionSegmentContextDto
{
    /** @var ?array<string|bool|int|float|null> */
    public $custom = null;

    /** @var ?TransactionContextRequestDto */
    public $request = null;

    /** @var ?TransactionContextUserDto */
    public $user = null;

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
                if (parent::deserializeKeyValue($key, $value, $result)) {
                    return true;
                }

                switch ($key) {
                    case 'custom':
                        $result->custom = self::assertValidCustom(self::deserializeApmMap($value));
                        return true;
                    case 'request':
                        $result->request = TransactionContextRequestDto::deserialize($value);
                        return true;
                    case 'user':
                        $result->user = TransactionContextUserDto::deserialize($value);
                        return true;
                    default:
                        return false;
                }
            }
        );

        $result->assertValid();
        return $result;
    }

    /**
     * @param Optional<?array<string|bool|int|float|null>> $expected
     * @param mixed                                                   $actual
     *
     * @phpstan-assert ?array<string|bool|int|float|null> $actual
     */
    private static function assertCustomMatches(Optional $expected, $actual): void
    {
        self::assertKeyValueMapsMatch($expected, $actual, /* shouldKeyValueStringsBeKeyword */ false);
    }

    /**
     * @param mixed $actual
     *
     * @return ?array<string|bool|int|float|null>
     */
    private static function assertValidCustom($actual): ?array
    {
        /**
         * @var Optional<?array<string|bool|int|float|null>> $expected
         * @noinspection PhpRedundantVariableDocTypeInspection
         */
        $expected = new Optional();
        self::assertCustomMatches($expected, $actual);
        return $actual;
    }

    public function assertMatches(TransactionContextExpectations $expectations): void
    {
        parent::assertMatchesExecutionSegment($expectations);

        if ($this->custom !== null) {
            self::assertCustomMatches($expectations->custom, $this->custom);
        }
        if ($this->request !== null) {
            $this->request->assertValid();
        }
        if ($this->user !== null) {
            $this->user->assertValid();
        }
    }

    public function assertValid(): void
    {
        $this->assertMatches(new TransactionContextExpectations());
    }
}
