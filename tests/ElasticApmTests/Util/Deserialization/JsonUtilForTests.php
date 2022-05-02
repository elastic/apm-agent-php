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

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\JsonUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class JsonUtilForTests
{
    use StaticClassTrait;

    /**
     * @param string $inputJson
     *
     * @return string
     */
    public static function prettyFormat(string $inputJson): string
    {
        return JsonUtil::encode(JsonUtil::decode($inputJson, /* asAssocArray */ true), /* prettyPrint: */ true);
    }

    /**
     * @param mixed $value
     */
    public static function assertJsonDeserializable($value, string $dbgPathToValue): void
    {
        $dbgInfoAboutValue = LoggableToString::convert(
            ['$dbgPathToValue' => $dbgPathToValue, '$value type' => DbgUtil::getType($value), '$value' => $value]
        );

        TestCase::assertThat(
            $value,
            TestCase::logicalOr(
                new IsType(IsType::TYPE_ARRAY),
                new IsType(IsType::TYPE_BOOL),
                new IsType(IsType::TYPE_FLOAT),
                new IsType(IsType::TYPE_INT),
                new IsType(IsType::TYPE_NULL),
                new IsType(IsType::TYPE_OBJECT),
                new IsType(IsType::TYPE_STRING)
            ),
            $dbgInfoAboutValue
        );

        if (is_array($value)) {
            foreach ($value as $arrKey => $arrVal) {
                self::assertJsonDeserializable($arrVal, $dbgPathToValue . '[' . $arrKey . ']');
            }
        } elseif (is_object($value)) {
            TestCase::assertInstanceOf(JsonDeserializableInterface::class, $value, $dbgInfoAboutValue);
        }
    }
}
