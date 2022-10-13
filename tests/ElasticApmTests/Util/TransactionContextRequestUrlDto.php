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

final class TransactionContextRequestUrlDto
{
    use AssertValidTrait;

    /** @var ?string */
    public $domain = null;

    /** @var ?string */
    public $full = null;

    /** @var ?string */
    public $original = null;

    /** @var ?string */
    public $path = null;

    /** @var ?int */
    public $port = null;

    /** @var ?string */
    public $protocol = null;

    /** @var ?string */
    public $query = null;

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
                    case 'full':
                        $result->full = self::assertValidNullableKeywordString($value);
                        return true;
                    case 'hostname':
                        $result->domain = self::assertValidNullableKeywordString($value);
                        return true;
                    case 'pathname':
                        $result->path = self::assertValidNullableKeywordString($value);
                        return true;
                    case 'port':
                        $result->port = self::assertValidNullablePort($value);
                        return true;
                    case 'protocol':
                        $result->protocol = self::assertValidNullableKeywordString($value);
                        return true;
                    case 'raw':
                        $result->original = self::assertValidNullableKeywordString($value);
                        return true;
                    case 'search':
                        $result->query = self::assertValidNullableKeywordString($value);
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
     * @param mixed $value
     *
     * @return ?int
     */
    private static function assertValidNullablePort($value): ?int
    {
        if ($value === null) {
            return null;
        }

        TestCase::assertIsInt($value);
        /** @var int $value */
        return $value;
    }

    public function assertValid(): void
    {
        self::assertValidNullableKeywordString($this->domain);
        self::assertValidNullableKeywordString($this->full);
        self::assertValidNullableKeywordString($this->original);
        self::assertValidNullableKeywordString($this->path);
        self::assertValidNullablePort($this->port);
        self::assertValidNullableKeywordString($this->protocol);
        self::assertValidNullableKeywordString($this->query);
    }
}
