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

namespace ElasticApmTests\UnitTests\UtilTests;

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\IdValidationUtil;
use Elastic\Apm\Impl\Util\UrlUtil;
use PHPUnit\Framework\TestCase;

class IdValidationUtilTest extends TestCase
{
    /**
     * @return array<array{string, int, bool}>
     */
    public function dataProviderForTestIsValidHexNumberString(): array
    {
        return [
            ['1234', 2, true],
            ['1234', 3, false],
            ['1234', 1, false],
            ['abcdef', 3, true],
            ['AbCdEf', 3, true],
            ['0123456789AbCdEf', 8, true],
            ['abcd', 2, true],
            ['zabc', 2, false],
            ['abcz', 2, false],
        ];
    }

    /**
     * @dataProvider dataProviderForTestIsValidHexNumberString
     */
    public function testIsValidHexNumberString(
        string $numberAsString,
        int $expectedSizeInBytes,
        bool $expectedResult
    ): void {
        $actualResult = IdValidationUtil::isValidHexNumberString($numberAsString, $expectedSizeInBytes);
        self::assertSame(
            $expectedResult,
            $actualResult,
            LoggableToString::convert(
                [
                    'numberAsString'      => $numberAsString,
                    'expectedSizeInBytes' => $expectedSizeInBytes,
                    'expectedResult'      => $expectedResult,
                    '$actualResult'       => $actualResult,
                ]
            )
        );
    }
}
