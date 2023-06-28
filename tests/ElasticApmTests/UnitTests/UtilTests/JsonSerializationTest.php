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

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\JsonUtilForTests;
use ElasticApmTests\Util\TestCaseBase;

class JsonSerializationTest extends TestCaseBase
{
    public function testMapWithNumericKeys(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);

        $original = ['0' => 0];
        $serialized = SerializationUtil::serializeAsJson((object)$original);
        $dbgCtx->add(['serialized' => $serialized]);
        self::assertSame(1, preg_match('/^\s*{\s*"0"\s*:\s*0\s*}\s*$/', $serialized));
        $decodedJson = JsonUtilForTests::decode($serialized, /* asAssocArray */ true);
        self::assertIsArray($decodedJson);
        self::assertEqualMaps($original, $decodedJson);
    }
}
