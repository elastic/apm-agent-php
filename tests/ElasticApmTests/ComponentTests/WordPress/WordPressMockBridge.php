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

namespace ElasticApmTests\ComponentTests\WordPress;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\AutoInstrument\WordPressFilterCallbackWrapper;
use Elastic\Apm\Impl\StackTraceFrame;
use Elastic\Apm\Impl\TransactionContext;
use Elastic\Apm\Impl\Util\RangeUtil;
use Elastic\Apm\Impl\Util\StackTraceUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\ComponentTests\Util\AmbientContextForTests;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\WordPressAutoInstrumentationTest;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\FileUtilForTests;
use ElasticApmTests\Util\MixedMap;
use ElasticApmTests\Util\StackTraceFrameExpectations;
use ElasticApmTests\Util\TestCaseBase;
use stdClass;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class WordPressMockBridge
{
    use StaticClassTrait;

    /** @var mixed */
    public static $activeTheme = null;

    /** @var null|array<mixed> */
    public static $expectedCallbackArgs = null;

    /** @var mixed */
    public static $expectedCallbackReturnValue = null;

    /** @var int */
    public static $mockMuPluginCallbackCallsCount = 0;

    /** @var null|StackTraceFrame[] */
    public static $mockMuPluginCallbackFirstCallStackTrace = null;

    /** @var int */
    public static $mockPluginCallbackCallsCount = 0;

    /** @var null|StackTraceFrame[] */
    public static $mockPluginCallbackFirstCallStackTrace = null;

    /** @var int */
    public static $mockThemeCallbackCallsCount = 0;

    /** @var null|StackTraceFrame[] */
    public static $mockThemeCallbackFirstCallStackTrace = null;

    /** @var int */
    public static $mockPartOfCoreCallbackCallsCount = 0;

    /** @var null|StackTraceFrame[] */
    public static $mockPartOfCoreCallbackFirstCallStackTrace = null;

    /** @var mixed */
    public const EXPECTED_WORDPRESS_VERSION_DEFAULT = '6.2';

    /** @var mixed */
    public static $expectedWordPressVersion = self::EXPECTED_WORDPRESS_VERSION_DEFAULT;

    /** @var ?bool */
    public static $shouldExpectCallbacksToBeWrapped = null;

    /** @var array<callable(): void> */
    public static $removeFilterCalls = [];

    /** @var StackTraceFrame */
    public static $expectedCallbackStackTraceTopFrame;

    public const MOCK_MU_PLUGIN_HOOK_NAME = 'save_post';
    public const MOCK_PLUGIN_HOOK_NAME = 'media_upload_newtab';
    public const MOCK_THEME_HOOK_NAME = 'set_header_font';
    public const MOCK_PART_OF_CORE_HOOK_NAME = 'update_footer';

    public const MOCK_MU_PLUGIN_NAME = 'my_mock_mu_plugin';
    public const MOCK_PLUGIN_NAME = 'my_mock_plugin';
    public const MOCK_THEME_NAME = 'my_mock_theme';

    public static function loadMockSource(string $srcVariantBaseDir, bool $isExpectedVariant): void
    {
        $wpIncludesDir = $srcVariantBaseDir . DIRECTORY_SEPARATOR . WordPressAutoInstrumentationTest::WP_INCLUDES_DIR_NAME;
        require $wpIncludesDir . DIRECTORY_SEPARATOR . 'plugin.php';
        require $wpIncludesDir . DIRECTORY_SEPARATOR . 'theme.php';

        if ($isExpectedVariant) {
            return;
        }

        require $wpIncludesDir . DIRECTORY_SEPARATOR . 'version.php';

        $wpContentDir = $srcVariantBaseDir . DIRECTORY_SEPARATOR . 'wp-content';
        require FileUtilForTests::listToPath([$wpContentDir, 'mu-plugins', self::MOCK_MU_PLUGIN_NAME . '.php']);
        require FileUtilForTests::listToPath([$wpContentDir, 'plugins', self::MOCK_PLUGIN_NAME, 'main.php']);
        require FileUtilForTests::listToPath([$wpContentDir, 'themes', self::MOCK_THEME_NAME, 'index.php']);
        require FileUtilForTests::listToPath([$srcVariantBaseDir, 'wp-admin', 'part_of_core.php']);
    }

    /**
     * @return mixed
     *
     * @noinspection PhpReturnDocTypeMismatchInspection
     */
    private static function callWpGetTemplate()
    {
        /**
         * @noinspection PhpFullyQualifiedNameUsageInspection
         * @phpstan-ignore-next-line
         */
        return \get_template();
    }

    /**
     * @param string $hookName
     * @param mixed  $firstArg
     * @param mixed  ...$args
     *
     * @return mixed
     */
    private static function callWpApplyFilters(string $hookName, $firstArg, ...$args)
    {
        /**
         * @noinspection PhpFullyQualifiedNameUsageInspection
         * @phpstan-ignore-next-line
         */
        return \apply_filters($hookName, $firstArg, ...$args);
    }

    /**
     * @param string $hookName
     * @param mixed  ...$args
     */
    private static function callWpDoAction(string $hookName, ...$args): void
    {
        /**
         * @noinspection PhpFullyQualifiedNameUsageInspection
         * @phpstan-ignore-next-line
         */
        \do_action($hookName, ...$args);
    }

    public static function runMockSource(MixedMap $appCodeArgs): void
    {
        $activeTheme = $appCodeArgs->getNullableString(WordPressAutoInstrumentationTest::EXPECTED_THEME_KEY);
        $muPluginCallsCount = $appCodeArgs->getInt(WordPressAutoInstrumentationTest::MU_PLUGIN_CALLS_COUNT_KEY);
        $pluginCallsCount = $appCodeArgs->getInt(WordPressAutoInstrumentationTest::PLUGIN_CALLS_COUNT_KEY);
        $themeCallsCount = $appCodeArgs->getInt(WordPressAutoInstrumentationTest::THEME_CALLS_COUNT_KEY);
        $partOfCoreCallsCount = $appCodeArgs->getInt(WordPressAutoInstrumentationTest::PART_OF_CORE_CALLS_COUNT_KEY);
        self::$shouldExpectCallbacksToBeWrapped = WordPressAutoInstrumentationTest::isWordPressDataToBeExpected($appCodeArgs);

        // Have instrumented get_template() return non-string value first to test that instrumentation handles it correctly
        WordPressMockBridge::$activeTheme = false;
        TestCaseBase::assertSame(self::$activeTheme, self::callWpGetTemplate());
        WordPressMockBridge::$activeTheme = $activeTheme;
        TestCaseBase::assertSame(self::$activeTheme, self::callWpGetTemplate());

        $applyFiltersCallsCount = 0;
        $doActionCallsCount = 0;
        $callIndex = 0;

        $invokeCallbacks = function (string $hookName) use (&$callIndex, &$applyFiltersCallsCount, &$doActionCallsCount): void {
            AssertMessageStack::newScope(/* out */ $dbgCtx);
            $dbgCtx->add(['callIndex' => $callIndex, 'applyFiltersCallsCount' => $applyFiltersCallsCount, 'doActionCallsCount' => $doActionCallsCount]);
            $shouldUseApplyFilters = ($callIndex % 2) === 0;
            $dbgCtx->add(['shouldUseApplyFilters' => $shouldUseApplyFilters]);
            // do_action allows no arguments but apply_filters requires at least one argument
            $argsCount = ($callIndex % 3) + ($shouldUseApplyFilters ? 1 : 0);
            $dbgCtx->add(['argsCount' => $argsCount]);
            $args = [];
            foreach (RangeUtil::generateUpTo($argsCount) as $i) {
                switch ($i % 4) {
                    case 0:
                        $args[] = 'dummy string arg';
                        break;
                    case 1:
                        $args[] = 98761234;
                        break;
                    case 2:
                        $args[] = 3.1416;
                        break;
                    case 3:
                        $args[] = new stdClass();
                        break;
                }
            }
            $dbgCtx->add(['args' =>  $args]);
            TestCaseBase::assertCount($argsCount, $args);

            self::$expectedCallbackArgs = $args;
            self::$expectedCallbackReturnValue = ($callIndex % 5) === 0 ? new stdClass() : 'dummy string return value';
            if ($shouldUseApplyFilters) {
                $actualRetVal = self::callWpApplyFilters($hookName, ...$args);
                ++$applyFiltersCallsCount;
                TestCaseBase::assertSame(self::$expectedCallbackReturnValue, $actualRetVal);
            } else {
                self::callWpDoAction($hookName, ...$args);
                ++$doActionCallsCount;
            }
            self::$expectedCallbackArgs = null;
            self::$expectedCallbackReturnValue = null;
            ++$callIndex;
        };

        foreach (RangeUtil::generateUpTo($muPluginCallsCount) as $ignored) {
            $invokeCallbacks(self::MOCK_MU_PLUGIN_HOOK_NAME);
        }

        foreach (RangeUtil::generateUpTo($pluginCallsCount) as $ignored) {
            $invokeCallbacks(self::MOCK_PLUGIN_HOOK_NAME);
        }

        foreach (RangeUtil::generateUpTo($themeCallsCount) as $ignored) {
            $invokeCallbacks(self::MOCK_THEME_HOOK_NAME);
        }

        foreach (RangeUtil::generateUpTo($partOfCoreCallsCount) as $ignored) {
            $invokeCallbacks(self::MOCK_PART_OF_CORE_HOOK_NAME);
        }

        self::invokeRemoveFilterCalls();

        $txCtx = ElasticApm::getCurrentTransaction()->context();
        $txCtx->setLabel(WordPressAutoInstrumentationTest::APPLY_FILTERS_CALLS_COUNT_KEY, $applyFiltersCallsCount);
        $txCtx->setLabel(WordPressAutoInstrumentationTest::DO_ACTION_CALLS_COUNT_KEY, $doActionCallsCount);
        $txCtx->setLabel(WordPressAutoInstrumentationTest::MU_PLUGIN_CALLS_COUNT_KEY, self::$mockMuPluginCallbackCallsCount);
        $txCtx->setLabel(WordPressAutoInstrumentationTest::PLUGIN_CALLS_COUNT_KEY, self::$mockPluginCallbackCallsCount);
        $txCtx->setLabel(WordPressAutoInstrumentationTest::THEME_CALLS_COUNT_KEY, self::$mockThemeCallbackCallsCount);
        $txCtx->setLabel(WordPressAutoInstrumentationTest::PART_OF_CORE_CALLS_COUNT_KEY, self::$mockPartOfCoreCallbackCallsCount);

        TestCaseBase::assertInstanceOf(TransactionContext::class, $txCtx);
        /** @var TransactionContext $txCtx */

        /**
         * @param string                 $key
         * @param null|StackTraceFrame[] $stackTrace
         * @param int                    $callsCount
         *
         * @return void
         */
        $setContextCustomWithStackTrace = function (string $key, ?array $stackTrace, int $callsCount) use ($txCtx): void {
            if (!self::$shouldExpectCallbacksToBeWrapped || $callsCount === 0) {
                TestCaseBase::assertNull($stackTrace);
                return;
            }
            TestCaseBase::assertNotNull($stackTrace);
            ComponentTestCaseBase::setContextCustom($txCtx, $key, $stackTrace);
        };

        $setContextCustomWithStackTrace(WordPressAutoInstrumentationTest::MU_PLUGIN_CALLBACK_FIRST_CALL_STACK_TRACE_KEY, self::$mockMuPluginCallbackFirstCallStackTrace, $muPluginCallsCount);
        $setContextCustomWithStackTrace(WordPressAutoInstrumentationTest::PLUGIN_CALLBACK_FIRST_CALL_STACK_TRACE_KEY, self::$mockPluginCallbackFirstCallStackTrace, $pluginCallsCount);
        $setContextCustomWithStackTrace(WordPressAutoInstrumentationTest::THEME_CALLBACK_FIRST_CALL_STACK_TRACE_KEY, self::$mockThemeCallbackFirstCallStackTrace, $themeCallsCount);
        $setContextCustomWithStackTrace(WordPressAutoInstrumentationTest::PART_OF_CORE_CALLBACK_FIRST_CALL_STACK_TRACE_KEY, self::$mockPartOfCoreCallbackFirstCallStackTrace, $partOfCoreCallsCount);
    }

    /**
     * @param array<mixed> $actualArgs
     */
    public static function assertCallbackArgsAsExpected(array $actualArgs): void
    {
        TestCaseBase::assertNotNull(self::$expectedCallbackArgs);
        TestCaseBase::assertEqualLists(self::$expectedCallbackArgs, $actualArgs);
    }

    public static function callWpFilterBuildUniqueId(string $hookName, callable $callback, int $priority): ?string
    {
        /**
         * @noinspection PhpFullyQualifiedNameUsageInspection
         * @phpstan-ignore-next-line
         */
        return \_wp_filter_build_unique_id($hookName, $callback, $priority);
    }

    private static function newWpHook(): WordPressMockWpHook
    {
        /**
         * @noinspection PhpFullyQualifiedNameUsageInspection
         * @phpstan-ignore-next-line
         */
        return new \WP_Hook();
    }

    /**
     * @param array<string, WordPressMockWpHook> &$wpFilterGlobal
     * @param string                              $hookName
     * @param callable                            $callback
     * @param int                                 $priority
     * @param int                                 $acceptedArgsCount
     */
    public static function mockImplAddFiler(array &$wpFilterGlobal, string $hookName, callable $callback, int $priority, int $acceptedArgsCount): void
    {
        self::assertInvariant($wpFilterGlobal);

        if (!array_key_exists($hookName, $wpFilterGlobal)) {
            $wpFilterGlobal[$hookName] = self::newWpHook();
        }

        /**
         * @phpstan-ignore-next-line
         */
        $wpFilterGlobal[$hookName]->add_filter($hookName, $callback, $priority, $acceptedArgsCount);

        self::assertInvariant($wpFilterGlobal);
    }

    /**
     * @param array<string, WordPressMockWpHook> &$wpFilterGlobal
     * @param string                              $hookName
     * @param callable                            $callback
     * @param int                                 $priority
     *
     * @return bool
     */
    public static function mockImplRemoveFilter(array &$wpFilterGlobal, string $hookName, callable $callback, int $priority): bool
    {
        self::assertInvariant($wpFilterGlobal);

        if (!array_key_exists($hookName, $wpFilterGlobal)) {
            return false;
        }

        $wpHook = $wpFilterGlobal[$hookName];
        $wpHook->mockImplRemoveFilter($hookName, $callback, $priority);
        if ($wpHook->mockImplIsEmpty()) {
            unset($wpFilterGlobal[$hookName]);
        }

        self::assertInvariant($wpFilterGlobal);
        return true;
    }

    /**
     * @param array<string, WordPressMockWpHook> $wpFilterGlobal
     * @param string                             $hookName
     * @param mixed                              $firstArg
     * @param mixed                              ...$restOfArgs
     *
     * @return mixed
     */
    public static function mockImplApplyFilters(array $wpFilterGlobal, string $hookName, $firstArg, ...$restOfArgs)
    {
        self::assertInvariant($wpFilterGlobal);

        if (!array_key_exists($hookName, $wpFilterGlobal)) {
            return $firstArg;
        }

        $allArgs = $restOfArgs;
        array_unshift(/* ref */ $allArgs, $firstArg);

        return $wpFilterGlobal[$hookName]->mockApplyFilters($allArgs);
    }

    /**
     * @param array<string, WordPressMockWpHook> $wpFilterGlobal
     * @param string                             $hookName
     * @param mixed                              ...$args
     */
    public static function mockImplDoAction(array $wpFilterGlobal, string $hookName, ...$args): void
    {
        self::assertInvariant($wpFilterGlobal);

        if (!array_key_exists($hookName, $wpFilterGlobal)) {
            return;
        }

        $wpFilterGlobal[$hookName]->mockApplyFilters($args);
    }

    /**
     * @param null|StackTraceFrame[] &$stackTrace
     *
     * @return void
     */
    public static function setCallbackStackTrace(/* ref */ ?array &$stackTrace): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);

        if (self::$shouldExpectCallbacksToBeWrapped && $stackTrace === null) {
            /**
             * numberOfStackFramesToSkip is 4 to skip
             *      1) This method
             *      2) callback
             *      3) call_user_func in WordPressFilterCallbackWrapper::__invoke
             *      4) WordPressFilterCallbackWrapper::__invoke
             */
            $stackTrace = (new StackTraceUtil(AmbientContextForTests::loggerFactory()))->captureInApmFormat(/* numberOfStackFramesToSkip */ 4, /* maxNumberOfFrames */ null);
            $dbgCtx->add(['stackTrace' => $stackTrace]);
            TestCaseBase::assertCountAtLeast(1, $stackTrace);
            StackTraceFrameExpectations::fromFrame(self::$expectedCallbackStackTraceTopFrame)->assertMatches($stackTrace[0]);
        }
    }

    /**
     * @param array<string, WordPressMockWpHook> $wpFilterGlobal
     *
     * @return int
     */
    private static function getFilterCount(array $wpFilterGlobal): int
    {
        $result = 0;
        foreach ($wpFilterGlobal as $wpHook) {
            $result += $wpHook->getFilterCount();
        }
        return $result;
    }

    /**
     * @param array<string, WordPressMockWpHook> $wpFilterGlobal
     */
    private static function assertInvariant(array $wpFilterGlobal): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $dbgCtx->add(['$shouldExpectCallbacksToBeWrapped' => self::$shouldExpectCallbacksToBeWrapped]);

        if (self::$shouldExpectCallbacksToBeWrapped === null) {
            return;
        }

        $dbgCtx->add(['WordPressFilterCallbackWrapper' => ['ctorCalls' => WordPressFilterCallbackWrapper::$ctorCalls, 'dtorCalls' => WordPressFilterCallbackWrapper::$dtorCalls]]);
        if (self::$shouldExpectCallbacksToBeWrapped) {
            $filterCount = self::getFilterCount($wpFilterGlobal);
            TestCaseBase::assertSame($filterCount, WordPressFilterCallbackWrapper::$ctorCalls - WordPressFilterCallbackWrapper::$dtorCalls);
        } else {
            TestCaseBase::assertSame(0, WordPressFilterCallbackWrapper::$ctorCalls);
            TestCaseBase::assertSame(0, WordPressFilterCallbackWrapper::$dtorCalls);
        }
    }

    private static function invokeRemoveFilterCalls(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);

        global $wp_filter;
        self::assertInvariant($wp_filter);

        foreach (self::$removeFilterCalls as $removeFilterCalls) {
            $removeFilterCalls();
        }

        self::assertInvariant($wp_filter);
        $dbgCtx->add(['wp_filter' => $wp_filter]);
        $dbgCtx->add(['WordPressFilterCallbackWrapper' => ['ctorCalls' => WordPressFilterCallbackWrapper::$ctorCalls, 'dtorCalls' => WordPressFilterCallbackWrapper::$dtorCalls]]);
        TestCaseBase::assertSame(0, WordPressFilterCallbackWrapper::$ctorCalls - WordPressFilterCallbackWrapper::$dtorCalls);
    }
}
