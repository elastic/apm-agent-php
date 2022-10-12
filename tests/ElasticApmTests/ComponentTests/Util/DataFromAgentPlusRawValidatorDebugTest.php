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

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\Util\JsonUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\TestsRootDir;
use ElasticApmTests\Util\FileUtilForTests;
use ElasticApmTests\Util\FlakyAssertions;
use ElasticApmTests\Util\TestCaseBase;

final class DataFromAgentPlusRawValidatorDebugTest extends TestCaseBase
{
    public function testToDebugDataFromAgentPlusRawValidator(): void
    {
        FlakyAssertions::setEnabled(true);

        $repoRootFullPath = FileUtilForTests::normalizePath(
            FileUtilForTests::listToPath([TestsRootDir::$fullPath, '..'])
        );
        $inputFileFullPath = FileUtilForTests::listToPath(
            [$repoRootFullPath, 'z_local', 'DataFromAgentPlusRawValidatorDebugTest_input.txt']
        );
        if (!file_exists($inputFileFullPath)) {
            self::dummyAssert();
            return;
        }

        $inputFileContents = file_get_contents($inputFileFullPath);
        self::assertNotFalse($inputFileContents);
        if (TextUtil::isEmptyString($inputFileContents)) {
            self::dummyAssert();
            return;
        }

        $inputFileContentsDecoded = JsonUtil::decode($inputFileContents, /* asAssocArray */ true);
        self::assertIsArray($inputFileContentsDecoded);

        $expectations = self::unserializeDecodedJsonSubObj(
            $inputFileContentsDecoded,
            TestCaseHandle::SERIALIZED_EXPECTATIONS_KEY
        );
        self::assertInstanceOf(DataFromAgentPlusRawExpectations::class, $expectations);
        $dataFromAgent = self::unserializeDecodedJsonSubObj(
            $inputFileContentsDecoded,
            TestCaseHandle::SERIALIZED_DATA_FROM_AGENT_KEY
        );
        self::assertInstanceOf(DataFromAgentPlusRaw::class, $dataFromAgent);
        DataFromAgentPlusRawValidator::validate($dataFromAgent, $expectations);
    }

    /**
     * @param array<mixed, mixed> $decodedJson
     * @param string              $propName
     *
     * @return object
     */
    private static function unserializeDecodedJsonSubObj(array $decodedJson, string $propName): object
    {
        $subObjDecodedJson = $decodedJson[$propName];
        self::assertIsString($subObjDecodedJson);
        $subObj = unserialize($subObjDecodedJson);
        self::assertIsObject($subObj);
        return $subObj;
    }
}
