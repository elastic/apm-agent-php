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

namespace ElasticApmTests\UnitTests\ConfigTests;

use Elastic\Apm\Impl\Util\SingletonInstanceTrait;

/**
 * @implements OptionTestValuesGeneratorInterface<array<string|bool|int|float|null>>
 */
final class LabelsOptionTestValuesGenerator implements OptionTestValuesGeneratorInterface
{
    use SingletonInstanceTrait;

    /** @inheritDoc */
    public function validValues(): iterable
    {
        /** @var array<string, array<string|bool|int|float|null>> $rawToParsed */
        $rawToParsed = [
            ''                                                => [],
            'key=string_value'                                => ['key' => 'string_value'],
            'key_with_empty_string_value='                    => ['key_with_empty_string_value' => ''],
            'key=null'                                        => ['key' => null],
            'key=123'                                         => ['key' => 123],
            'key=456.5'                                       => ['key' => 456.5],
            " \n  key1 =  \n true \t, \t  key2 \n=  false \t" => ['key1' => true, 'key2' => false],
        ];
        foreach ($rawToParsed as $raw => $parsed) {
            yield new OptionTestValidValue($raw, $parsed);
        }
    }

    /** @inheritDoc */
    public function invalidRawValues(): iterable
    {
        return [
            " key", // no key-value separator
            "key=value, \t key_without_separator", // no key-value separator
            " key \t=value1, \t key \n =value2", // same key more than once
        ];
    }
}
