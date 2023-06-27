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

use Elastic\Apm\Impl\Util\BoolUtil;
use ElasticApmTests\Util\BoolUtilForTests;
use PHPUnit\Framework\TestCase;

class BoolUtilTest extends TestCase
{
    public function testIfThen(): void
    {
        self::assertTrue(BoolUtil::ifThen(true, true));
        self::assertTrue(BoolUtil::ifThen(false, true));
        self::assertTrue(BoolUtil::ifThen(false, false));

        self::assertTrue(!BoolUtil::ifThen(true, false));
    }

    public function testToInt(): void
    {
        self::assertSame(1, BoolUtil::toInt(true));
        self::assertSame(0, BoolUtil::toInt(false));
    }

    public function testToString(): void
    {
        self::assertSame('true', BoolUtil::toString(true));
        self::assertSame('false', BoolUtil::toString(false));
    }

    public function testFromString(): void
    {
        foreach ([true, false] as $boolVal) {
            self::assertSame($boolVal, BoolUtilForTests::fromString(BoolUtil::toString($boolVal)));
        }
    }
}
