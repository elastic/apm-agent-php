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

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\Impl\AutoInstrument\WordPressAutoInstrumentation;
use Elastic\Apm\Impl\Log\LoggableToString;
use ElasticApmTests\Util\TestCaseBase;

class WordPressAutoInstrumentationUnitTest extends TestCaseBase
{
    private static function findPluginSubDirNameInStackTraceFrameFilePathTestImpl(
        string $filePath,
        ?string $expectedPluginSubDirName
    ): void {
        $adaptedFilePath = ((DIRECTORY_SEPARATOR === '/')
                ? $filePath
                : str_replace('/', DIRECTORY_SEPARATOR, $filePath));
        $actualPluginSubDirName
            = WordPressAutoInstrumentation::findPluginSubDirNameInStackTraceFrameFilePath($adaptedFilePath);
        $ctx = [
            'filePath' => $filePath,
            'adaptedFilePath' => $adaptedFilePath,
            'expectedPluginSubDirName' => $expectedPluginSubDirName,
            'actualPluginSubDirName' => $actualPluginSubDirName,
        ];
        self::assertSame($expectedPluginSubDirName, $actualPluginSubDirName, LoggableToString::convert($ctx));
    }

    public function testFindPluginSubDirNameInStackTraceFrameFilePath(): void
    {
        /** @var callable(string, ?string): void $testImpl */
        $testImpl = [__CLASS__, 'findPluginSubDirNameInStackTraceFrameFilePathTestImpl'];

        $testImpl('/var/www/html/wp-content/plugins/hello-dolly/hello.php', 'hello-dolly');
        $testImpl('/var/www/html/wp-content/plugins/hello-dolly/', 'hello-dolly');
        $testImpl('/var/www/html/wp-content/plugins/hello-dolly', null);
        $testImpl('/wp-content/plugins/hello-dolly/hello.php', 'hello-dolly');
        $testImpl('wp-content/plugins/hello-dolly/hello.php', null);

        $testImpl('', null);
        $testImpl('/', null);
        $testImpl('//', null);
        $testImpl('/abc', null);
    }
}
