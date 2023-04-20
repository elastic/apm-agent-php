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
use ElasticApmTests\ComponentTests\WordPressAutoInstrumentationTest;
use ElasticApmTests\Util\AssertMessageBuilder;
use ElasticApmTests\Util\TestCaseBase;

class WordPressAutoInstrumentationUnitTest extends TestCaseBase
{
    private static function findAddonNameInStackTraceFrameFilePathTestImpl(string $filePath, ?string $expectedAddonName): void
    {
        $adaptedFilePath = ((DIRECTORY_SEPARATOR === '/')
            ? $filePath
            : str_replace('/', DIRECTORY_SEPARATOR, $filePath));
        $actualPluginSubDirName
            = WordPressAutoInstrumentation::findAddonNameInStackTraceFrameFilePath($adaptedFilePath);
        $ctx = [
            'filePath'               => $filePath,
            'adaptedFilePath'        => $adaptedFilePath,
            'expectedAddonName'      => $expectedAddonName,
            'actualPluginSubDirName' => $actualPluginSubDirName,
        ];
        self::assertSame($expectedAddonName, $actualPluginSubDirName, LoggableToString::convert($ctx));
    }

    public function testFindAddonNameInStackTraceFrameFilePath(): void
    {
        $testImpl = function (string $filePath, ?string $expectedPluginSubDirName): void {
            self::findAddonNameInStackTraceFrameFilePathTestImpl($filePath, $expectedPluginSubDirName);
        };

        $testImpl('/var/www/html/wp-content/plugins/hello-dolly/hello.php', 'hello-dolly');
        $testImpl('/var/www/html/wp-content/plugins/hello-dolly/', 'hello-dolly');
        $testImpl('/var/www/html/wp-content/plugins/hello-dolly', null);
        $testImpl('/wp-content/plugins/hello-dolly/hello.php', 'hello-dolly');
        $testImpl('wp-content/plugins/hello-dolly/hello.php', null);
        $testImpl('/wp-content/plugins/hello.php', 'hello');

        $testImpl('/var/www/html/wp-content/mu-plugins/hello-dolly/hello.php', 'hello-dolly');
        $testImpl('/var/www/html/wp-content/mu-plugins/hello-dolly/', 'hello-dolly');
        $testImpl('/var/www/html/wp-content/mu-plugins/hello-dolly', null);
        $testImpl('/wp-content/mu-plugins/hello-dolly/hello.php', 'hello-dolly');
        $testImpl('wp-content/mu-plugins/hello-dolly/hello.php', null);
        $testImpl('/wp-content/mu-plugins/hello.php', 'hello');

        $testImpl('/var/www/html/wp-content/themes/hello-dolly/hello.php', 'hello-dolly');
        $testImpl('/var/www/html/wp-content/themes/hello-dolly/', 'hello-dolly');
        $testImpl('/var/www/html/wp-content/themes/hello-dolly', null);
        $testImpl('/wp-content/themes/hello-dolly/hello.php', 'hello-dolly');
        $testImpl('wp-content/themes/hello-dolly/hello.php', null);
        $testImpl('/wp-content/themes/hello.php', 'hello');

        $testImpl('', null);
        $testImpl('/', null);
        $testImpl('//', null);
        $testImpl('/abc', null);
    }

    private static function findThemeNameFromDirPathTestImpl(string $themeDirPath, ?string $expectedThemeName): void
    {
        $actualThemeName = WordPressAutoInstrumentation::findThemeNameFromDirPath($themeDirPath);
        $ctx = ['themeDirPath' => $themeDirPath, 'expectedThemeName' => $expectedThemeName, 'actualThemeName' => $actualThemeName];
        self::assertSame($expectedThemeName, $actualThemeName, LoggableToString::convert($ctx));
    }

    public function testFindThemeNameFromDirPath(): void
    {
        $testImpl = function (string $themeDirPath, ?string $expectedThemeName): void {
            self::findThemeNameFromDirPathTestImpl($themeDirPath, $expectedThemeName);
        };

        $testImpl('/var/www/html/wp-content/themes/generatepress', 'generatepress');
        $testImpl('/var/www/html/wp-content/themes/generatepress/', 'generatepress');
        $testImpl('/var/www/html/wp-content/themes/generatepress/.', '.');
        $testImpl('/var/www/html/wp-content/themes/generatepress/./', '.');
        $testImpl('generatepress', 'generatepress');
        $testImpl('generatepress/', 'generatepress');
        $testImpl('/.', '.');
        $testImpl('.', '.');
        $testImpl('/./', '.');

        $testImpl('/', null);
        $testImpl('', null);
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function dataProviderForTestFoldTextWithMarkersIntoOneLine(): iterable
    {
        yield ['', ''];
        yield ['some text', 'some text'];

        $indent = "\t \t";
        $whiteSpace = "\t\t  \t\t";
        $whiteSpaceBeforeBeginMarker = "\t\t\t  \t\t\t  \t\t\t";
        $input
            = $indent . 'Original line 1' . PHP_EOL .
              $indent . 'Original line 2' . $whiteSpaceBeforeBeginMarker . WordPressAutoInstrumentationTest::FOLD_INTO_ONE_LINE_BEGIN_MARKER . $whiteSpace . PHP_EOL .
              $indent . 'Injected into line 2 - part A' . $whiteSpace . PHP_EOL .
              $indent . 'Injected into line 2 - part B' . $whiteSpace . PHP_EOL .
              $indent . WordPressAutoInstrumentationTest::FOLD_INTO_ONE_LINE_END_MARKER . $whiteSpace . PHP_EOL .
              $indent . 'Original line 3' . PHP_EOL;
        $expectedOutput
            = $indent . 'Original line 1' . PHP_EOL .
              $indent . 'Original line 2' . $whiteSpaceBeforeBeginMarker . ' ' . 'Injected into line 2 - part A' . ' ' . 'Injected into line 2 - part B' . PHP_EOL .
              $indent . 'Original line 3' . PHP_EOL;
        yield [$input, $expectedOutput];
        yield [$expectedOutput, $expectedOutput];
    }

    /**
     * @dataProvider dataProviderForTestFoldTextWithMarkersIntoOneLine
     *
     * @param string $input
     * @param string $expectedOutput
     */
    public static function testFoldTextWithMarkersIntoOneLine(string $input, string $expectedOutput): void
    {
        $actualOutput = WordPressAutoInstrumentationTest::adaptManuallyInstrumentedSourceFileContent($input);
        self::assertSame($expectedOutput, $actualOutput, (new AssertMessageBuilder(['input' => $input]))->s());
    }
}
