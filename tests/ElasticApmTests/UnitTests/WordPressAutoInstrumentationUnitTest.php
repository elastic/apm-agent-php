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
use ElasticApmTests\ComponentTests\Util\AmbientContextForTests;
use ElasticApmTests\ComponentTests\WordPressAutoInstrumentationTest;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\TestCaseBase;
use stdClass;

use const ElasticApmTests\DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_FILE_NAME;

class WordPressAutoInstrumentationUnitTest extends TestCaseBase
{
    public function testFindAddonNameInFilePath(): void
    {
        $testImpl = function (string $filePath, string $expectedGroupKind, ?string $expectedGroupName): void {
            AssertMessageStack::newScope(/* out */ $dbgCtx);

            $adaptedFilePath = ((DIRECTORY_SEPARATOR === '/') ? $filePath : str_replace('/', DIRECTORY_SEPARATOR, $filePath));
            $actualGroupKind = 'dummy actualGroupKind';
            $actualGroupName = 'dummy actualGroupName';
            WordPressAutoInstrumentation::findAddonInfoFromFilePath($adaptedFilePath, AmbientContextForTests::loggerFactory(), /* out */ $actualGroupKind, /* out */ $actualGroupName);
            $dbgCtx->add(
                [
                    'filePath'          => $filePath,
                    'adaptedFilePath'   => $adaptedFilePath,
                    'expectedGroupKind' => $expectedGroupKind,
                    'actualGroupKind'   => $actualGroupKind,
                    'expectedGroupName' => $expectedGroupName,
                    'actualGroupName'   => $actualGroupName,
                ]
            );
            self::assertSame($expectedGroupKind, $actualGroupKind);
            self::assertSame($expectedGroupName, $actualGroupName);
        };

        $pluginFilePathToExpectedName = [
            '/var/www/html/wp-content/plugins/hello-dolly/hello.php' => 'hello-dolly',
            '/var/www/html/wp-content/plugins/hello-dolly/'          => 'hello-dolly',
            '/wp-content/plugins/hello-dolly/hello.php'              => 'hello-dolly',
            '/wp-content/plugins/hello.php'                          => 'hello',

            '/var/www/html/wp-content/mu-plugins/hello-dolly/hello.php' => 'hello-dolly',
            '/var/www/html/wp-content/mu-plugins/hello-dolly/'          => 'hello-dolly',
            '/wp-content/mu-plugins/hello-dolly/hello.php'              => 'hello-dolly',
            '/wp-content/mu-plugins/hello.php'                          => 'hello',
            'mock_src/wp-content/mu-plugins/my_mock_mu_plugin.php'      => 'my_mock_mu_plugin',
        ];
        foreach ($pluginFilePathToExpectedName as $filePath => $expectedGroupName) {
            $testImpl($filePath, /* $expectedGroupKind */ WordPressAutoInstrumentation::CALLBACK_GROUP_KIND_PLUGIN, $expectedGroupName);
        }

        $themeFilePathToExpectedName = [
            '/var/www/html/wp-content/themes/hello-dolly/hello.php' => 'hello-dolly',
            '/var/www/html/wp-content/themes/hello-dolly/'          => 'hello-dolly',
            '/wp-content/themes/hello-dolly/hello.php'              => 'hello-dolly',
            '/wp-content/themes/hello.php'                          => 'hello',
        ];
        foreach ($themeFilePathToExpectedName as $filePath => $expectedGroupName) {
            $testImpl($filePath, /* $expectedGroupKind */ WordPressAutoInstrumentation::CALLBACK_GROUP_KIND_THEME, $expectedGroupName);
        }

        $coreFilePaths = [
            'wp-content/plugins/hello-dolly/hello.php',
            '/var/www/html/wp-content/plugins/hello-dolly',
            '/var/www/html/wp-content/mu-plugins/hello-dolly',
            'wp-content/mu-plugins/hello-dolly/hello.php',
            '/var/www/html/wp-content/themes/hello-dolly',
            'wp-content/themes/hello-dolly/hello.php',
            '',
            '/',
            '//',
            '/abc',
        ];
        foreach ($coreFilePaths as $filePath) {
            $testImpl($filePath, /* $expectedGroupKind */ WordPressAutoInstrumentation::CALLBACK_GROUP_KIND_CORE, /* expectedGroupName */ null);
        }
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
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['input' => $input, 'expectedOutput' => $expectedOutput]);

        $actualOutput = WordPressAutoInstrumentationTest::foldTextWithMarkersIntoOneLine($input);
        self::assertSame($expectedOutput, $actualOutput);
    }

    public static function dummyStaticMethodForTestGetCallbackSourceFilePath(): string
    {
        return __FUNCTION__;
    }

    public function dummyMethodForTestGetCallbackSourceFilePath(): string
    {
        return __FUNCTION__;
    }

    /**
     * @param string  $methodName
     * @param mixed[] $args
     */
    public function __call(string $methodName, array $args): void
    {
    }

    /**
     * @param string  $methodName
     * @param mixed[] $args
     */
    public static function __callStatic(string $methodName, array $args): void
    {
    }

    public function testGetCallbackSourceFilePath(): void
    {
        $testImpl =
            /**
             * @param mixed   $callback
             * @param ?string $expectedResult
             */
            function ($callback, ?string $expectedResult): void {
                AssertMessageStack::newScope(/* out */ $dbgCtx);
                $dbgCtx->add(['callback' => $callback, 'expectedResult' => $expectedResult]);
                $actualResult = WordPressAutoInstrumentation::getCallbackSourceFilePath($callback, AmbientContextForTests::loggerFactory());
                $dbgCtx->add(['actualResult' => $actualResult]);
                self::assertSame($expectedResult, $actualResult);
            };

        $testImpl('\dummyFuncForTestsWithoutNamespace', DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_FILE_NAME);
        $testImpl('dummyFuncForTestsWithoutNamespace', DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_FILE_NAME);
        $testImpl('\ElasticApmTests\dummyFuncForTestsWithNamespace', DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_FILE_NAME);
        $testImpl('ElasticApmTests\dummyFuncForTestsWithNamespace', DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_FILE_NAME);

        $testImpl(__CLASS__ . '::' . self::dummyStaticMethodForTestGetCallbackSourceFilePath(), __FILE__);
        $testImpl([__CLASS__, self::dummyStaticMethodForTestGetCallbackSourceFilePath()], __FILE__);

        $testImpl(__CLASS__ . '::' . self::dummyMethodForTestGetCallbackSourceFilePath(), __FILE__);
        $testImpl([__CLASS__, self::dummyMethodForTestGetCallbackSourceFilePath()], __FILE__);
        $testImpl([$this, self::dummyMethodForTestGetCallbackSourceFilePath()], __FILE__);

        $testImpl(__CLASS__ . '::' . 'implictNonExistentMethod', __FILE__);
        $testImpl([__CLASS__, 'implictNonExistentMethod'], __FILE__);
        $testImpl([$this, 'implictNonExistentMethod'], __FILE__);

        $dummyClosure = function () {
        };
        $testImpl($dummyClosure, __FILE__);

        $testImpl('', null);
        $testImpl(null, null);
        $testImpl(new stdClass(), null);
        $testImpl(1, null);
        $testImpl([stdClass::class, 'implictNonExistentMethod'], null);
        $testImpl('invalid name', null);
        $testImpl([], null);
        $testImpl([1], null);
        $testImpl([1, 2], null);
        $testImpl(['a', 'b'], null);
        $testImpl(['a'], null);
        $testImpl([stdClass::class], null);
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function dataProviderForTestRemoveAttributes(): iterable
    {
        yield ['', ''];
        yield ['some text', 'some text'];

        $input
            = '#[MyDummyAttribute]' . PHP_EOL .
              'function get_template()' . PHP_EOL;
        $expectedOutput
            = 'function get_template()' . PHP_EOL;
        yield [$input, $expectedOutput];
        yield [$expectedOutput, $expectedOutput];

        $input
            = '{ #[MyDummyAttribute]' . PHP_EOL .
              'function get_template()' . PHP_EOL;
        $expectedOutput
            = '{ function get_template()' . PHP_EOL;
        yield [$input, $expectedOutput];
        yield [$expectedOutput, $expectedOutput];
    }

    /**
     * @dataProvider dataProviderForTestRemoveAttributes
     *
     * @param string $input
     * @param string $expectedOutput
     */
    public static function testRemoveAttributes(string $input, string $expectedOutput): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['input' => $input, 'expectedOutput' => $expectedOutput]);

        $actualOutput = WordPressAutoInstrumentationTest::removeAttributes($input);
        self::assertSame($expectedOutput, $actualOutput);
    }
}
