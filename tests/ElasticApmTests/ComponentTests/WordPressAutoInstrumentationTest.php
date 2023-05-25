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

/** @noinspection RequiredAttributes */

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests;

use Elastic\Apm\Impl\AutoInstrument\WordPressAutoInstrumentation;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\NameVersionData;
use Elastic\Apm\Impl\StackTraceFrame;
use Elastic\Apm\Impl\Util\RangeUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\ComponentTests\Util\AmbientContextForTests;
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\AutoInstrumentationUtilForTests;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\ExpectedEventCounts;
use ElasticApmTests\ComponentTests\WordPress\WordPressSpanExpectationsBuilder;
use ElasticApmTests\ComponentTests\WordPress\WordPressMockBridge;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\FileUtilForTests;
use ElasticApmTests\Util\LogCategoryForTests;
use ElasticApmTests\Util\MetadataExpectations;
use ElasticApmTests\Util\MixedMap;
use ElasticApmTests\Util\SpanExpectations;
use ElasticApmTests\Util\SpanSequenceValidator;
use ElasticApmTests\Util\StackTraceExpectations;
use ElasticApmTests\Util\TextUtilForTests;
use SplFileInfo;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class WordPressAutoInstrumentationTest extends ComponentTestCaseBase
{
    private const EXPECTED_SERVICE_FRAMEWORK_NAME = 'WordPress';

    public const WP_INCLUDES_DIR_NAME = 'wp-includes';

    public const EXPECTED_LABEL_KEY_FOR_WORDPRESS_THEME = 'wordpress_theme';

    private const SRC_VARIANTS_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'WordPress';

    private const IS_AST_PROCESS_ENABLED_KEY = 'is_ast_process_enabled';
    private const IS_AST_PROCESS_DEBUG_DUMP_ENABLED_KEY = 'is_ast_process_debug_dump_enabled';
    public const EXPECTED_THEME_KEY = 'expected_theme';
    public const MU_PLUGIN_CALLS_COUNT_KEY = 'mu_plugin_calls_count';
    public const PLUGIN_CALLS_COUNT_KEY = 'plugin_calls_count';
    public const THEME_CALLS_COUNT_KEY = 'theme_calls_count';
    public const PART_OF_CORE_CALLS_COUNT_KEY = 'part_of_core_calls_count';

    public const APPLY_FILTERS_CALLS_COUNT_KEY = 'apply_filters_count_calls';
    public const DO_ACTION_CALLS_COUNT_KEY = 'do_action_count_calls';

    private const SRC_VARIANT_DIR_KEY = 'src_variant_dir';
    private const IS_EXPECTED_VARIANT_KEY = 'is_expected_variant';

    private const ADAPTED_SOURCE_DIR_NAME = 'adapted_source';
    public const FOLD_INTO_ONE_LINE_BEGIN_MARKER = '/* <<< BEGIN Elasitc APM tests marker to fold into one line */';
    public const FOLD_INTO_ONE_LINE_END_MARKER = '/* >>> END Elasitc APM tests marker to fold into one line */';

    private const DEBUG_DUMP_DIR_NAME = 'debug_dump';

    private const DEBUG_DUMP_FILE_EXTENSION = 'txt';
    private const BEFORE_AST_PROCESS_FILE_NAME_SUFFIX = 'before_AST_process';
    private const AFTER_AST_PROCESS_FILE_NAME_SUFFIX = 'after_AST_process';

    private const CONVERTED_BACK_TO_SOURCE_FILE_EXTENSION = 'php';
    private const AST_CONVERTED_BACK_TO_SOURCE_FILE_NAME_SUFFIX = 'converted_back_to_source';

    private const ADAPTED_FILE_NAME_SUFFIX = 'adapted';
    private const SUFFIX_TO_BE_REMOVED_BY_ELASTIC_APM_TESTS = '_suffixToBeRemovedByElasticApmTests';

    private const EXPECTED_WORDPRESS_VERSION_KEY = 'expected_wordpress_version';

    public const MU_PLUGIN_CALLBACK_STACK_TRACE_KEY = 'mu_plugin_callback_stack_trace';
    public const PLUGIN_CALLBACK_STACK_TRACE_KEY = 'plugin_callback_stack_trace';
    public const THEME_CALLBACK_STACK_TRACE_KEY = 'theme_callback_stack_trace';
    public const PART_OF_CORE_CALLBACK_STACK_TRACE_KEY = 'part_of_core_callback_stack_trace';

    private const NON_KEYWORD_STRING_MAX_LENGTH = 100 * 1024;

    /**
     * @return string[]
     */
    private static function expectedFilesToBeAstTransformed(): array
    {
        /**
         * @see src/ext/WordPress_instrumentation.c
         */

        $result = [];
        $result[] = self::WP_INCLUDES_DIR_NAME . DIRECTORY_SEPARATOR . 'plugin.php';
        $result[] = self::WP_INCLUDES_DIR_NAME . DIRECTORY_SEPARATOR . 'class-wp-hook.php';
        $result[] = self::WP_INCLUDES_DIR_NAME . DIRECTORY_SEPARATOR . 'theme.php';
        return $result;
    }

    private static function getLoggerForThisClass(): Logger
    {
        static $logger = null;
        if ($logger === null) {
            $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST, __NAMESPACE__, __CLASS__, __FILE__);
        }
        return $logger;
    }

    public function testIsAutoInstrumentationEnabled(): void
    {
        // In production code ELASTIC_APM_WORDPRESS_DIRECT_CALL_METHOD_SET_READY_TO_WRAP_FILTER_CALLBACKS is defined by the native part of the agent
        // but if we don't load elastic_apm extension in the component tests so we need to define a dummy
        $constantName = 'ELASTIC_APM_WORDPRESS_DIRECT_CALL_METHOD_SET_READY_TO_WRAP_FILTER_CALLBACKS';
        if (!defined($constantName)) {
            define($constantName, 'dummy unused value');
        }

        self::implTestIsAutoInstrumentationEnabled(WordPressAutoInstrumentation::class, /* expectedNames */ ['wordpress']);
    }

    private static function buildInputOrExpectedOutputVariantSubDir(string $baseDir, bool $isExpectedVariant): string
    {
        return $baseDir . DIRECTORY_SEPARATOR . ($isExpectedVariant ? 'expected_process_AST_output' : 'mock_src');
    }

    public static function removeAttributes(string $fileContents): string
    {
        $adaptedLines = [];
        foreach (TextUtilForTests::iterateLinesEx($fileContents) as [$line, $endOfLine]) {
            $line = trim($line);

            if (TextUtil::isSuffixOf(']', $line)) {
                $attrStartPos = strpos($line, '#[');
                if ($attrStartPos !== false) {
                    $line = substr($line, /* offset */ 0, $attrStartPos);
                    $endOfLine = '';
                }
            }

            $adaptedLines[] = $line . $endOfLine;
        }

        return implode(/* separator */ '', $adaptedLines);
    }

    public static function foldTextWithMarkersIntoOneLine(string $fileContents): string
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['fileContents' => $fileContents]);

        $adaptedLines = [];
        $isBetweenMarkers = false;
        foreach (TextUtilForTests::iterateLinesEx($fileContents) as [$line, $endOfLine]) {
            if ($isBetweenMarkers) {
                if (($endMarkerStartPos = strpos($line, WordPressAutoInstrumentationTest::FOLD_INTO_ONE_LINE_END_MARKER)) !== false) {
                    // Verify that line with end marker does not have any other non-whitespace text
                    self::assertTrue(TextUtil::isEmptyString(trim(substr($line, /* offset */ 0, $endMarkerStartPos))));
                    self::assertTrue(TextUtil::isEmptyString(trim(substr($line, /* offset */ $endMarkerStartPos + strlen(WordPressAutoInstrumentationTest::FOLD_INTO_ONE_LINE_END_MARKER)))));
                    $adaptedLines[] = $endOfLine;
                    $isBetweenMarkers = false;
                    continue;
                }

                $line = trim($line);

                // Attributes were introduced in PHP 8 and earlier PHP versions interpret the rest of the line after # as a comment
                if (PHP_MAJOR_VERSION < 8 && TextUtil::isPrefixOf('#', $line)) {
                    continue;
                }

                if (!TextUtil::isEmptyString($line)) {
                    $adaptedLines[] = ' ' . $line;
                }
                continue;
            }

            if (($beginMarkerStartPos = strpos($line, WordPressAutoInstrumentationTest::FOLD_INTO_ONE_LINE_BEGIN_MARKER)) !== false) {
                $isBetweenMarkers = true;
                // Add part before begin marker start position
                $adaptedLines[] = substr($line, /* offset */ 0, $beginMarkerStartPos);
                // Verify that there is no non-whitespace text after marker end
                $partAfterMarkerEnd = substr($line, /* offset */ $beginMarkerStartPos + strlen(WordPressAutoInstrumentationTest::FOLD_INTO_ONE_LINE_BEGIN_MARKER));
                $dbgCtx->add(['partAfterMarkerEnd' => $partAfterMarkerEnd]);
                self::assertTrue(TextUtil::isEmptyString(trim($partAfterMarkerEnd)));
                continue;
            }

            $adaptedLines[] = $line . $endOfLine;
        }

        return implode(/* separator */ '', $adaptedLines);
    }

    private static function adaptSourceFileContent(bool $isExpectedVariant, string $fileContents): string
    {
        $adaptedFileContents = $fileContents;

        // Attributes were introduced in PHP 8 and earlier PHP versions interpret the rest of the line after # as a comment
        if (PHP_MAJOR_VERSION < 8) {
            $adaptedFileContents = self::removeAttributes($adaptedFileContents);
        }

        if ($isExpectedVariant) {
            $adaptedFileContents = self::foldTextWithMarkersIntoOneLine($adaptedFileContents);
        }

        return $adaptedFileContents;
    }

    private static function adaptSourceTree(bool $isExpectedVariant, string $fromDir, string $toDir): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['isExpectedVariant' => $isExpectedVariant, 'fromDir' => $fromDir, 'toDir' => $toDir]);

        $logger = self::getLoggerForThisClass();
        $loggerProxyDebug = $logger->ifDebugLevelEnabledNoLine(__FUNCTION__);

        self::assertNotFalse($fromDirEntries = scandir($fromDir));
        $dbgCtx->pushSubScope();
        foreach ($fromDirEntries as $entryName) {
            if ($entryName == '.' || $entryName == '..') {
                continue;
            }
            $dbgCtx->clearCurrentSubScope(['entryName' => $entryName]);
            $fromDirEntryFullPath = $fromDir . DIRECTORY_SEPARATOR . $entryName;
            if (is_dir($fromDirEntryFullPath)) {
                $toSubDirFullPath = $toDir . DIRECTORY_SEPARATOR . $entryName;
                $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Creating directory...', ['toSubDirFullPath' => $toSubDirFullPath]);
                self::assertTrue(mkdir($toSubDirFullPath));
                self::adaptSourceTree($isExpectedVariant, $fromDirEntryFullPath, $toSubDirFullPath);
                continue;
            }

            $srcFileInfo = new SplFileInfo($fromDirEntryFullPath);
            if (!($srcFileInfo->isFile() && ($srcFileInfo->getExtension() === 'php'))) {
                continue;
            }
            $srcFileRelPath = FileUtilForTests::convertPathRelativeTo($fromDirEntryFullPath, $fromDir);
            $adaptedSrcFileFullPath = FileUtilForTests::listToPath([$toDir, $srcFileRelPath]);
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Creating file...', ['adaptedSrcFileFullPath' => $adaptedSrcFileFullPath]);
            $dbgCtx->add(['fromDirEntryFullPath' => $fromDirEntryFullPath, 'adaptedSrcFileFullPath' => $adaptedSrcFileFullPath]);
            self::assertFileExists($fromDirEntryFullPath);
            self::assertNotFalse($srcFileContents = file_get_contents($fromDirEntryFullPath));
            $adaptedSrcFileContents = self::adaptSourceFileContent($isExpectedVariant, $srcFileContents);
            if ($adaptedSrcFileContents !== $srcFileContents) {
                ($loggerProxy = $logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Contents of ' . $adaptedSrcFileFullPath . ':' . "\n" . $adaptedSrcFileContents);
            }
            self::assertNotFalse(file_put_contents($adaptedSrcFileFullPath, $adaptedSrcFileContents));
            self::assertFileExists($adaptedSrcFileFullPath);
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Created file', ['adaptedSrcFileFullPath' => $adaptedSrcFileFullPath]);
        }
        $dbgCtx->popSubScope();
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public function dataProviderForTestAstProcessOnMockSource(): iterable
    {
        $result = (new DataProviderForTestBuilder())
            ->addBoolKeyedDimensionAllValuesCombinable(self::IS_AST_PROCESS_ENABLED_KEY)
            ->addBoolKeyedDimensionAllValuesCombinable(self::IS_AST_PROCESS_DEBUG_DUMP_ENABLED_KEY)
            ->build();

        return DataProviderForTestBuilder::convertEachDataSetToMixedMap(self::adaptKeyValueToSmoke($result));
    }

    /**
     * @dataProvider dataProviderForTestAstProcessOnMockSource
     */
    public function testAstProcessOnMockSource(MixedMap $testArgs): void
    {
        $subDirName = FileUtilForTests::buildTempSubDirName(__CLASS__, __FUNCTION__);
        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTestWithArtgs(__CLASS__, __FUNCTION__, $testArgs),
            function () use ($subDirName, $testArgs): void {
                $tempOutDir = FileUtilForTests::createTempSubDir($subDirName);
                try {
                    $this->implTestAstProcessOnMockSource($tempOutDir, $testArgs);
                } finally {
                    FileUtilForTests::deleteTempSubDir($subDirName);
                }
            }
        );
    }

    private static function removeSuffixToBeRemovedByElasticApmTests(string $fileContents): string
    {
        $adaptedLines = [];
        foreach (TextUtilForTests::iterateLines($fileContents, /* keepEndOfLine */ true) as $line) {
            $adaptedLines[] = str_replace(self::SUFFIX_TO_BE_REMOVED_BY_ELASTIC_APM_TESTS, '', $line);
        }

        return implode(/* separator */ '', $adaptedLines);
    }

    private static function adaptManuallyInstrumentedGeneratedFile(/* in,out */ string &$filePath, /* in,out */ string &$fileContents): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['filePath' => $filePath, 'fileContents' => $fileContents]);

        $fileInfo = new SplFileInfo($filePath);
        $adaptedFileName = $fileInfo->getBasename($fileInfo->getExtension()) . self::ADAPTED_FILE_NAME_SUFFIX . '.' . $fileInfo->getExtension();
        $adaptedFilePath = $fileInfo->getPath() . DIRECTORY_SEPARATOR . $adaptedFileName;
        $adaptedFileContents = $fileContents;
        $adaptedFileContents = self::removeSuffixToBeRemovedByElasticApmTests($adaptedFileContents);
        $dbgCtx->add(['adaptedFilePath' => $adaptedFilePath, 'adaptedFileContents' => $adaptedFileContents]);
        self::assertNotFalse(file_put_contents($adaptedFilePath, $adaptedFileContents));
        $filePath = $adaptedFilePath;
        $fileContents = $adaptedFileContents;
    }

    private static function logFileContentOnMismatch(string $filePath, string $fileContents): void
    {
        $logger = self::getLoggerForThisClass();
        $loggerProxy = $logger->ifCriticalLevelEnabledNoLine(__FUNCTION__);
        if ($loggerProxy === null) {
            return;
        }

        $loggerProxy->log(__LINE__, 'Content of ' . $filePath . ' begin [length: ' . strlen($fileContents) . ']:' . PHP_EOL . $fileContents);
        $loggerProxy->log(__LINE__, 'Content of ' . $filePath . ' end');
    }

    private static function verifyAstProcessGeneratedFiles(string $astProcessDebugDumpOutDir, string $phpFileRelativePath): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['astProcessDebugDumpOutDir' => $astProcessDebugDumpOutDir, 'phpFileRelativePath' => $phpFileRelativePath]);

        $logger = self::getLoggerForThisClass()->addAllContext(['astProcessDebugDumpOutDir' => $astProcessDebugDumpOutDir, 'phpFileRelativePath' => $phpFileRelativePath]);

        /**
         * @param-out string $fileFullPath
         * @param-out string $fileContents
         */
        $getGeneratedFileContents = function (
            bool $isExpectedVariant,
            bool $isAstDebugDump,
            ?string &$fileFullPath,
            ?string &$fileContents
        ) use (
            $astProcessDebugDumpOutDir,
            $phpFileRelativePath
        ): void {
            AssertMessageStack::newScope(/* out */ $dbgCtx);
            $dbgCtx->add(['isExpectedVariant' => $isExpectedVariant, 'isAstDebugDump' => $isAstDebugDump, 'fileFullPath' => $fileFullPath]);

            $outSubDir = self::buildInputOrExpectedOutputVariantSubDir($astProcessDebugDumpOutDir, $isExpectedVariant);
            $fileName = $phpFileRelativePath . '.' . ($isExpectedVariant ? self::BEFORE_AST_PROCESS_FILE_NAME_SUFFIX : self::AFTER_AST_PROCESS_FILE_NAME_SUFFIX);
            $fileName .= '.' . ($isAstDebugDump ? self::DEBUG_DUMP_FILE_EXTENSION : (self::AST_CONVERTED_BACK_TO_SOURCE_FILE_NAME_SUFFIX . '.' . self::CONVERTED_BACK_TO_SOURCE_FILE_EXTENSION));

            $fileFullPath = FileUtilForTests::listToPath([$outSubDir, $fileName]);
            self::assertFileExists($fileFullPath);
            $fileContents = file_get_contents($fileFullPath);
            self::assertNotFalse($fileContents);
        };

        ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Starting...');

        $getGeneratedFileContents(/* isExpectedVariant */ true, /* isAstDebugDump */ true, /* out */ $expectedAstFilePath, /* out */ $expectedAstFileContents);
        self::adaptManuallyInstrumentedGeneratedFile(/* in,out */ $expectedAstFilePath, /* out */ $expectedAstFileContents);
        $getGeneratedFileContents(/* isExpectedVariant */ false, /* isAstDebugDump */ true, /* out */ $actualAstFilePath, /* out */ $actualAstFileContents);
        $astMatches = ($actualAstFileContents === $expectedAstFileContents);

        if (AmbientContextForTests::testConfig()->compareAstConvertedBackToSource) {
            $getGeneratedFileContents(/* isExpectedVariant */ true, /* isAstDebugDump */ false, /* out */ $expectedPhpFilePath, /* out */ $expectedPhpFileContents);
            self::adaptManuallyInstrumentedGeneratedFile(/* in,out */ $expectedPhpFilePath, /* out */ $expectedPhpFileContents);
            $getGeneratedFileContents(/* isExpectedVariant */ false, /* isAstDebugDump */ false, /* out */ $actualPhpFilePath, /* out */ $actualPhpFileContents);
            $phpMatches = ($actualPhpFileContents === $expectedPhpFileContents);
        } else {
            $phpMatches = true;
            $expectedPhpFilePath = '';
            $expectedPhpFileContents = '';
            $actualPhpFilePath = '';
            $actualPhpFileContents = '';
        }

        if ($astMatches && $phpMatches) {
            return;
        }

        $logCtx = ['astMatches' => $astMatches];
        if (AmbientContextForTests::testConfig()->compareAstConvertedBackToSource) {
            $logCtx['phpMatches'] = $phpMatches;
        }

        ($loggerProxy = $logger->ifCriticalLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Actual generated files do not match the expected', $logCtx);

        self::logFileContentOnMismatch($expectedAstFilePath, $expectedAstFileContents);
        if (AmbientContextForTests::testConfig()->compareAstConvertedBackToSource) {
            self::logFileContentOnMismatch($expectedPhpFilePath, $expectedPhpFileContents);
        }
        self::logFileContentOnMismatch($actualAstFilePath, $actualAstFileContents);
        if (AmbientContextForTests::testConfig()->compareAstConvertedBackToSource) {
            self::logFileContentOnMismatch($actualPhpFilePath, $actualPhpFileContents);
        }

        if (!$astMatches) {
            self::assertSame($expectedAstFilePath, $actualAstFilePath);
        } elseif (AmbientContextForTests::testConfig()->compareAstConvertedBackToSource) {
            self::assertSame($expectedPhpFilePath, $actualPhpFileContents);
        }
    }

    public static function appCodeForTestAstProcessOnMockSource(MixedMap $appCodeArgs): void
    {
        $srcVariantBaseDir = $appCodeArgs->getString(self::SRC_VARIANT_DIR_KEY);
        $isExpectedVariant = $appCodeArgs->getBool(self::IS_EXPECTED_VARIANT_KEY);
        WordPressMockBridge::loadMockSource($srcVariantBaseDir, $isExpectedVariant);
    }

    private function implTestAstProcessOnMockSource(string $tempOutDir, MixedMap $testArgs): void
    {
        $adaptedSrcBaseDir = FileUtilForTests::listToPath([$tempOutDir, self::ADAPTED_SOURCE_DIR_NAME]);
        $astProcessDebugDumpOutDir = FileUtilForTests::listToPath([$tempOutDir, self::DEBUG_DUMP_DIR_NAME]);
        self::assertTrue(mkdir($adaptedSrcBaseDir));

        $isAstProcessEnabled = $testArgs->getBool(self::IS_AST_PROCESS_ENABLED_KEY);
        $isAstProcessDebugDumpEnabled = $testArgs->getBool(self::IS_AST_PROCESS_DEBUG_DUMP_ENABLED_KEY);

        if ($isAstProcessEnabled) {
            MetadataExpectations::$serviceFrameworkDefault->setValue(new NameVersionData(self::EXPECTED_SERVICE_FRAMEWORK_NAME, WordPressMockBridge::EXPECTED_WORDPRESS_VERSION_DEFAULT));
        }

        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($adaptedSrcBaseDir, $astProcessDebugDumpOutDir, $isAstProcessEnabled, $isAstProcessDebugDumpEnabled): void {
                $appCodeParams->setAgentOptionIfNotDefaultValue(OptionNames::AST_PROCESS_ENABLED, $isAstProcessEnabled);
                $appCodeParams->setAgentOption(OptionNames::AST_PROCESS_DEBUG_DUMP_FOR_PATH_PREFIX, $adaptedSrcBaseDir);
                if ($isAstProcessDebugDumpEnabled) {
                    $appCodeParams->setAgentOption(OptionNames::AST_PROCESS_DEBUG_DUMP_OUT_DIR, $astProcessDebugDumpOutDir);
                }
                $appCodeParams->setAgentOptionIfNotDefaultValue(OptionNames::AST_PROCESS_DEBUG_DUMP_CONVERTED_BACK_TO_SOURCE, AmbientContextForTests::testConfig()->compareAstConvertedBackToSource);
            }
        );

        foreach ([true, false] as $isExpectedVariant) {
            $srcVariantBaseDir = self::buildInputOrExpectedOutputVariantSubDir(self::SRC_VARIANTS_DIR, $isExpectedVariant);
            $adaptedSrcVariantBaseDir = self::buildInputOrExpectedOutputVariantSubDir($adaptedSrcBaseDir, $isExpectedVariant);
            self::assertTrue(mkdir($adaptedSrcVariantBaseDir));
            self::adaptSourceTree($isExpectedVariant, $srcVariantBaseDir, $adaptedSrcVariantBaseDir);
            $appCodeHost->sendRequest(
                AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestAstProcessOnMockSource']),
                function (AppCodeRequestParams $appCodeRequestParams) use ($adaptedSrcVariantBaseDir, $isExpectedVariant): void {
                    $appCodeRequestParams->setAppCodeArgs([self::SRC_VARIANT_DIR_KEY => $adaptedSrcVariantBaseDir, self::IS_EXPECTED_VARIANT_KEY => $isExpectedVariant]);
                }
            );
        }

        if ($isAstProcessEnabled && $isAstProcessDebugDumpEnabled) {
            foreach (self::expectedFilesToBeAstTransformed() as $phpFileRelativePath) {
                self::verifyAstProcessGeneratedFiles($astProcessDebugDumpOutDir, $phpFileRelativePath);
            }
        } else {
            self::assertDirectoryDoesNotExist($astProcessDebugDumpOutDir);
        }
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public function dataProviderForTestOnMockSource(): iterable
    {
        $disableInstrumentationsVariants = [
            ''          => true,
            'WordPress' => false,
        ];

        $result = (new DataProviderForTestBuilder())
            ->addBoolKeyedDimensionOnlyFirstValueCombinable(self::IS_AST_PROCESS_ENABLED_KEY)
            ->addGeneratorOnlyFirstValueCombinable(AutoInstrumentationUtilForTests::disableInstrumentationsDataProviderGenerator($disableInstrumentationsVariants))
            ->addKeyedDimensionOnlyFirstValueCombinable(self::EXPECTED_THEME_KEY, ['my_favorite_theme', null, 'some_other_theme'])
            ->addKeyedDimensionOnlyFirstValueCombinable(self::PLUGIN_CALLS_COUNT_KEY, [10, 1, 2, 0])
            ->addKeyedDimensionOnlyFirstValueCombinable(self::MU_PLUGIN_CALLS_COUNT_KEY, [7, 1, 2, 0])
            ->addKeyedDimensionOnlyFirstValueCombinable(self::THEME_CALLS_COUNT_KEY, [13, 1, 2, 0])
            ->addKeyedDimensionOnlyFirstValueCombinable(self::PART_OF_CORE_CALLS_COUNT_KEY, [11, 1, 2, 0])
            ->build();

        return DataProviderForTestBuilder::convertEachDataSetToMixedMap(self::adaptKeyValueToSmoke($result));
    }

    public static function appCodeForTestOnMockSource(MixedMap $appCodeArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $srcVariantBaseDir = self::buildInputOrExpectedOutputVariantSubDir(self::SRC_VARIANTS_DIR, /* isExpectedVariant */ false);
        WordPressMockBridge::loadMockSource($srcVariantBaseDir, /* isExpectedVariant */ false);

        WordPressMockBridge::runMockSource($appCodeArgs);
    }

    private function implTestOnMockSource(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $isAstProcessEnabled = $testArgs->getBool(self::IS_AST_PROCESS_ENABLED_KEY);
        $disableInstrumentationsOptVal = $testArgs->getString(AutoInstrumentationUtilForTests::DISABLE_INSTRUMENTATIONS_KEY);
        $isInstrumentationEnabled = $testArgs->getBool(AutoInstrumentationUtilForTests::IS_INSTRUMENTATION_ENABLED_KEY);
        $isWordPressDataToBeExpected = $isAstProcessEnabled && $isInstrumentationEnabled;
        $expectedTheme = $testArgs->getNullableString(self::EXPECTED_THEME_KEY);
        $expectedMuPluginCallsCount = $testArgs->getInt(self::MU_PLUGIN_CALLS_COUNT_KEY);
        $expectedPluginCallsCount = $testArgs->getInt(self::PLUGIN_CALLS_COUNT_KEY);
        $expectedThemeCallsCount = $testArgs->getInt(self::THEME_CALLS_COUNT_KEY);
        $expectedPartOfCoreCallsCount = $testArgs->getInt(self::PART_OF_CORE_CALLS_COUNT_KEY);

        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($isAstProcessEnabled, $disableInstrumentationsOptVal): void {
                $appCodeParams->setAgentOptionIfNotDefaultValue(OptionNames::AST_PROCESS_ENABLED, $isAstProcessEnabled);
                $appCodeParams->setAgentOption(OptionNames::DISABLE_INSTRUMENTATIONS, $disableInstrumentationsOptVal);
                // Disable span compression to have all the expected spans individually
                $appCodeParams->setAgentOption(OptionNames::SPAN_COMPRESSION_ENABLED, false);
                $appCodeParams->setAgentOption(OptionNames::NON_KEYWORD_STRING_MAX_LENGTH, self::NON_KEYWORD_STRING_MAX_LENGTH);
            }
        );

        $expectationsBuilder = new WordPressSpanExpectationsBuilder();
        /** @var SpanExpectations[] $expectedSpans */
        $expectedSpans = [];
        if ($isWordPressDataToBeExpected) {
            MetadataExpectations::$serviceFrameworkDefault->setValue(new NameVersionData(self::EXPECTED_SERVICE_FRAMEWORK_NAME, WordPressMockBridge::EXPECTED_WORDPRESS_VERSION_DEFAULT));

            /**
             * @see WordPressMockBridge::runMockSource
             */
            foreach (RangeUtil::generateUpTo($expectedMuPluginCallsCount) as $ignored) {
                $expectedSpans[] = $expectationsBuilder->forPluginFilterCallback(WordPressMockBridge::MOCK_MU_PLUGIN_HOOK_NAME, WordPressMockBridge::MOCK_MU_PLUGIN_NAME);
            }
            foreach (RangeUtil::generateUpTo($expectedPluginCallsCount) as $ignored) {
                $expectedSpans[] = $expectationsBuilder->forPluginFilterCallback(WordPressMockBridge::MOCK_PLUGIN_HOOK_NAME, WordPressMockBridge::MOCK_PLUGIN_NAME);
            }
            foreach (RangeUtil::generateUpTo($expectedThemeCallsCount) as $ignored) {
                $expectedSpans[] = $expectationsBuilder->forThemeFilterCallback(WordPressMockBridge::MOCK_THEME_HOOK_NAME, WordPressMockBridge::MOCK_THEME_NAME);
            }
            foreach (RangeUtil::generateUpTo($expectedPartOfCoreCallsCount) as $ignored) {
                $expectedSpans[] = $expectationsBuilder->forCoreFilterCallback(WordPressMockBridge::MOCK_PART_OF_CORE_HOOK_NAME);
            }
        }
        $dbgCtx->add(['expectedSpans' => $expectedSpans]);

        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestOnMockSource']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($testArgs): void {
                $appCodeRequestParams->setAppCodeArgs($testArgs);
            }
        );

        $dataFromAgent = $testCaseHandle->waitForDataFromAgent((new ExpectedEventCounts())->transactions(1)->spans(count($expectedSpans)));
        $dbgCtx->add(['dataFromAgent' => $dataFromAgent]);

        $tx = $dataFromAgent->singleTransaction();
        $dbgCtx->add(['tx' => $tx]);

        if ((!$isWordPressDataToBeExpected) || ($expectedTheme === null)) {
            self::assertTrue($tx->context === null || $tx->context->labels === null || !array_key_exists(self::EXPECTED_LABEL_KEY_FOR_WORDPRESS_THEME, $tx->context->labels));
        } else {
            self::assertSame($expectedTheme, self::getLabel($tx, self::EXPECTED_LABEL_KEY_FOR_WORDPRESS_THEME));
        }

        $actualMuPluginCallsCount = self::getLabel($tx, self::MU_PLUGIN_CALLS_COUNT_KEY);
        self::assertSame($expectedMuPluginCallsCount, $actualMuPluginCallsCount);
        $actualPluginCallsCount = self::getLabel($tx, self::PLUGIN_CALLS_COUNT_KEY);
        self::assertSame($expectedPluginCallsCount, $actualPluginCallsCount);
        $actualThemeCallsCount = self::getLabel($tx, self::THEME_CALLS_COUNT_KEY);
        self::assertSame($expectedThemeCallsCount, $actualThemeCallsCount);
        $actualPartOfCoreCallsCount = self::getLabel($tx, self::PART_OF_CORE_CALLS_COUNT_KEY);
        self::assertSame($expectedPartOfCoreCallsCount, $actualPartOfCoreCallsCount);

        $applyFiltersCallsCount = self::getLabel($tx, self::APPLY_FILTERS_CALLS_COUNT_KEY);
        self::assertIsInt($applyFiltersCallsCount);
        $doActionCallsCount = self::getLabel($tx, self::DO_ACTION_CALLS_COUNT_KEY);
        self::assertIsInt($doActionCallsCount);
        self::assertSame($applyFiltersCallsCount + $doActionCallsCount, $expectedMuPluginCallsCount + $expectedPluginCallsCount + $actualThemeCallsCount + $expectedPartOfCoreCallsCount);

        if (!$isWordPressDataToBeExpected) {
            return;
        }

        $expectedSpanIndex = 0;
        $setExpectedSpanStackTrace = function (string $key, int $callsCount) use ($tx, $dbgCtx, $expectedSpans, &$expectedSpanIndex): void {
            self::assertNotNull($tx->context);
            if ($callsCount === 0) {
                if ($tx->context->custom !== null) {
                    self::assertArrayNotHasKey($key, $tx->context->custom);
                }
                return;
            }
            $stackTrace = ComponentTestCaseBase::getContextCustom($tx->context, $key);
            $dbgCtx->add(['stackTrace for ' . $key => $stackTrace]);
            self::assertIsArray($stackTrace);
            /** @var StackTraceFrame[] $stackTrace */
            self::assertFalse($expectedSpans[$expectedSpanIndex]->stackTrace->isValueSet());
            $expectedSpans[$expectedSpanIndex]->stackTrace->setValue(StackTraceExpectations::fromFrames($stackTrace, /* allowToBePrefixOfActual */ false));
            $expectedSpanIndex += $callsCount;
        };

        $setExpectedSpanStackTrace(self::MU_PLUGIN_CALLBACK_STACK_TRACE_KEY, $expectedMuPluginCallsCount);
        $setExpectedSpanStackTrace(self::PLUGIN_CALLBACK_STACK_TRACE_KEY, $expectedPluginCallsCount);
        $setExpectedSpanStackTrace(self::THEME_CALLBACK_STACK_TRACE_KEY, $expectedThemeCallsCount);
        $setExpectedSpanStackTrace(self::PART_OF_CORE_CALLBACK_STACK_TRACE_KEY, $expectedPartOfCoreCallsCount);

        SpanSequenceValidator::updateExpectationsEndTime($expectedSpans);
        SpanSequenceValidator::assertSequenceAsExpected($expectedSpans, array_values($dataFromAgent->idToSpan));
    }

    /**
     * @dataProvider dataProviderForTestOnMockSource
     */
    public function testOnMockSource(MixedMap $testArgs): void
    {
        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTestWithArtgs(__CLASS__, __FUNCTION__, $testArgs),
            function () use ($testArgs): void {
                $this->implTestOnMockSource($testArgs);
            }
        );
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public function dataProviderForTestFrameworkDiscovery(): iterable
    {
        $disableInstrumentationsVariants = [
            ''          => true,
            'WordPress' => false,
        ];

        $result = (new DataProviderForTestBuilder())
            ->addBoolKeyedDimensionAllValuesCombinable(self::IS_AST_PROCESS_ENABLED_KEY)
            ->addGeneratorOnlyFirstValueCombinable(AutoInstrumentationUtilForTests::disableInstrumentationsDataProviderGenerator($disableInstrumentationsVariants))
            ->addKeyedDimensionOnlyFirstValueCombinable(self::EXPECTED_WORDPRESS_VERSION_KEY, ['5.6', '6.2', 6, 6.1, null])
            ->build();

        return DataProviderForTestBuilder::convertEachDataSetToMixedMap(self::adaptKeyValueToSmoke($result));
    }

    public static function appCodeForTestFrameworkDiscovery(MixedMap $appCodeArgs): void
    {
        $srcVariantBaseDir = self::buildInputOrExpectedOutputVariantSubDir(self::SRC_VARIANTS_DIR, /* isExpectedVariant */ false);
        WordPressMockBridge::$expectedWordPressVersion = $appCodeArgs->get(self::EXPECTED_WORDPRESS_VERSION_KEY);
        WordPressMockBridge::loadMockSource($srcVariantBaseDir, /* isExpectedVariant */ false);

        /**
         * There should not be any need to actually run any of the loaded code
         */
    }

    private function implTestFrameworkDiscovery(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['testArgs' => $testArgs]);

        $isAstProcessEnabled = $testArgs->getBool(self::IS_AST_PROCESS_ENABLED_KEY);
        $disableInstrumentationsOptVal = $testArgs->getString(AutoInstrumentationUtilForTests::DISABLE_INSTRUMENTATIONS_KEY);
        $isInstrumentationEnabled = $testArgs->getBool(AutoInstrumentationUtilForTests::IS_INSTRUMENTATION_ENABLED_KEY);
        $expectedWordPressVersion = $testArgs->get(self::EXPECTED_WORDPRESS_VERSION_KEY);

        $expectedServiceFramework = $isAstProcessEnabled && $isInstrumentationEnabled && is_string($expectedWordPressVersion)
            ? new NameVersionData(self::EXPECTED_SERVICE_FRAMEWORK_NAME, $expectedWordPressVersion)
            : null;
        MetadataExpectations::$serviceFrameworkDefault->setValue($expectedServiceFramework);

        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($isAstProcessEnabled, $disableInstrumentationsOptVal): void {
                $appCodeParams->setAgentOptionIfNotDefaultValue(OptionNames::AST_PROCESS_ENABLED, $isAstProcessEnabled);
                $appCodeParams->setAgentOption(OptionNames::DISABLE_INSTRUMENTATIONS, $disableInstrumentationsOptVal);
            }
        );

        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestFrameworkDiscovery']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($testArgs): void {
                $appCodeRequestParams->setAppCodeArgs($testArgs);
            }
        );

        $dataFromAgent = $this->waitForOneEmptyTransaction($testCaseHandle);
        foreach ($dataFromAgent->metadatas as $metadata) {
            if ($expectedServiceFramework === null) {
                self::assertNull($metadata->service->framework);
            } else {
                self::assertNotNull($metadata->service->framework);
                self::assertSame($expectedServiceFramework->name, $metadata->service->framework->name);
                self::assertSame($expectedServiceFramework->version, $metadata->service->framework->version);
            }
        }
    }

    /**
     * @dataProvider dataProviderForTestFrameworkDiscovery
     */
    public function testFrameworkDiscovery(MixedMap $testArgs): void
    {
        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTestWithArtgs(__CLASS__, __FUNCTION__, $testArgs),
            function () use ($testArgs): void {
                $this->implTestFrameworkDiscovery($testArgs);
            }
        );
    }
}
