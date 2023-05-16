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

final class TransactionContextUserDto
{
    use AssertValidTrait;

    /** @var null|int|string */
    public $id;

    /** @var ?string */
    public $email;

    /** @var ?string */
    public $username;

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
                    case 'id':
                        $result->id = is_int($value) ? $value : self::assertValidNullableKeywordString($value);
                        return true;
                    case 'email':
                        $result->email = self::assertValidNullableKeywordString($value);
                        return true;
                    case 'username':
                        $result->username = self::assertValidNullableKeywordString($value);
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
        if (!is_int($this->id)) {
            self::assertValidNullableKeywordString($this->id);
        }
        self::assertValidNullableKeywordString($this->email);
        self::assertValidNullableKeywordString($this->username);
    }
}
