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

use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Util\IdGenerator;
use PHPUnit\Framework\TestCase;

class IdGeneratorTest extends TestCase
{
    /**
     * @return iterable<array{int}>
     */
    public function dataProviderForTestFormat(): iterable
    {
        yield [Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES];
        yield [Constants::TRACE_ID_SIZE_IN_BYTES];
        yield [Constants::ERROR_ID_SIZE_IN_BYTES];
    }

    /**
     * @dataProvider dataProviderForTestFormat
     */
    public function testFormat(int $idLengthInBytes): void
    {
        $id = IdGenerator::generateId($idLengthInBytes);
        self::assertSame($idLengthInBytes * 2, strlen($id));
        self::assertSame(1, preg_match('/^[0-9a-f]+$/', $id));
    }

    public function testUniqueEvenIfMtRandIsReseeded(): void
    {
        $count = 10000;
        $ids = [];
        for ($i = 0; $i < $count; ++$i) {
            // Each seeding would make mt_rand() produce the same sequence
            mt_srand(1);
            $ids[IdGenerator::generateId(Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES)] = true;
        }
        mt_srand();
        self::assertCount($count, $ids);
    }
}
