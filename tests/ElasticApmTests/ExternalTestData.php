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

namespace ElasticApmTests;

use Elastic\Apm\Impl\Util\JsonUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\Util\FileUtilForTests;

final class ExternalTestData
{
    use StaticClassTrait;

    /**
     * @param string[] $relativePathToSubDir
     * @param string   $relativePathToFile
     *
     * @return string
     */
    private static function fullPathForFileInSubDir(array $relativePathToSubDir, string $relativePathToFile): string
    {
        $fullPathAsList = [TestsRootDir::$fullPath];
        $fullPathAsList = array_merge($fullPathAsList, $relativePathToSubDir);
        $fullPathAsList[] = $relativePathToFile;
        return FileUtilForTests::normalizePath(FileUtilForTests::listToPath($fullPathAsList));
    }

    public static function fullPathForFileInApmServerIntakeApiSchemaDir(string $relativePathToFile): string
    {
        return self::fullPathForFileInSubDir(['APM_Server_intake_API_schema'], $relativePathToFile);
    }

    /**
     * @param string $relativePathToFile
     *
     * @return mixed
     */
    public static function readJsonSpecsFile(string $relativePathToFile)
    {
        $filePath = self::fullPathForFileInSubDir(['APM_Agents_shared', 'json-specs'], $relativePathToFile);

        $fileContent = '';
        FileUtilForTests::readLines(
            $filePath,
            function (string $line) use (&$fileContent): void {
                if (TextUtil::isPrefixOf('//', trim($line))) {
                    return;
                }
                $fileContent .= $line;
            }
        );

        return JsonUtil::decode($fileContent, /* asAssocArray */ true);
    }
}
