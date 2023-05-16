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

namespace ElasticApmTests\ComponentTests\UtilTests;

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\ArrayUtil;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\SelectPhpUnitConfigFile;
use ElasticApmTests\Util\TestCaseBase;
use PHPUnit\Runner\Version;

/**
 * @group does_not_require_external_services
 */
final class SelectPhpUnitConfigFileComponentTest extends TestCaseBase
{
    private const EXPECTED_CONFIG_FILES_PER_PHP_UNIT_MAJOR_VERSION = [
            SelectPhpUnitConfigFile::TESTS_TYPE_UNIT      => [8 => 'phpunit_v8_format.xml'],
            SelectPhpUnitConfigFile::TESTS_TYPE_COMPONENT => [8 => 'phpunit_component_tests_v8_format.xml'],
        ];

    private const EXPECTED_DEFAULT_CONFIG_FILES = [
            SelectPhpUnitConfigFile::TESTS_TYPE_UNIT      => 'phpunit.xml.dist',
            SelectPhpUnitConfigFile::TESTS_TYPE_COMPONENT => 'phpunit_component_tests.xml',
        ];

    public static function testAllExpectedFilesExist(): void
    {
        foreach (self::EXPECTED_CONFIG_FILES_PER_PHP_UNIT_MAJOR_VERSION as $phpUnitMajorVerionToFileName) {
            foreach ($phpUnitMajorVerionToFileName as $fileName) {
                self::assertFileExists($fileName);
            }
        }
    }

    private static function getCurrentPhpUnitMajorVersion(): int
    {
        $asDotSeparatedString = Version::id();
        AssertMessageStack::newScope(/* out */ $dbgCtx, ['asDotSeparatedString' => $asDotSeparatedString]);
        // Limit to 2 parts since we are only interested in MAJOR part of the version
        $partsAsStrings = explode(/* separator */ '.', $asDotSeparatedString, /* limit */ 2);
        $dbgCtx->add(['partsAsStrings' => $partsAsStrings]);
        self::assertGreaterThanOrEqual(1, count($partsAsStrings));
        $majorPartAsString = $partsAsStrings[0];
        $dbgCtx->add(['majorPartAsString' => $majorPartAsString]);
        self::assertNotFalse(filter_var($majorPartAsString, FILTER_VALIDATE_INT));
        return intval($majorPartAsString);
    }

    public static function testDiscoverPhpUnitMajorVersion(): void
    {
        self::assertSame(self::getCurrentPhpUnitMajorVersion(), SelectPhpUnitConfigFile::discoverPhpUnitMajorVersion());
    }

    private static function getExpectedPhpUnitConfigFile(string $testsType): string
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $phpUnitMajorVerion = self::getCurrentPhpUnitMajorVersion();
        $dbgCtx->add(['phpUnitMajorVerion' => $phpUnitMajorVerion]);

        $phpUnitMajorVerionToFileName = ArrayUtil::getValueIfKeyExistsElse($testsType, self::EXPECTED_CONFIG_FILES_PER_PHP_UNIT_MAJOR_VERSION, /* fallbackValue */ null);
        $dbgCtx->add(['phpUnitMajorVerionToFileName' => $phpUnitMajorVerionToFileName]);
        self::assertNotNull($phpUnitMajorVerionToFileName);

        $fileName = ArrayUtil::getValueIfKeyExistsElse($phpUnitMajorVerion, $phpUnitMajorVerionToFileName, null);
        return $fileName === null ? self::EXPECTED_DEFAULT_CONFIG_FILES[$testsType] : $fileName;
    }

    /**
     * @param iterable<array<string, mixed>> $srcDataProvider
     *
     * @return iterable<string, array<mixed>>
     */
    protected static function wrapDataProviderFromKeyValueMapToNamedDataSet(iterable $srcDataProvider): iterable
    {
        $dataSetIdex = 0;
        foreach ($srcDataProvider as $namedValuesMap) {
            $dataSetName = '#' . $dataSetIdex;
            $dataSetName .= ' ' . LoggableToString::convert($namedValuesMap);
            yield $dataSetName => array_values($namedValuesMap);
            ++$dataSetIdex;
        }
    }

    /**
     * @return iterable<array<string, string>>
     */
    private static function dataProviderForTestSelectPhpUnitConfigFileImpl(): iterable
    {
        foreach (SelectPhpUnitConfigFile::ALL_TESTS_TYPES as $testsType) {
            yield ['testsType' => $testsType];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function dataProviderForTestSelectPhpUnitConfigFile(): iterable
    {
        /** @var iterable<string, array{string}> $wrappedDataProvider */
        $wrappedDataProvider = self::wrapDataProviderFromKeyValueMapToNamedDataSet(
            self::dataProviderForTestSelectPhpUnitConfigFileImpl()
        );
        return $wrappedDataProvider;
    }

    /**
     * @dataProvider dataProviderForTestSelectPhpUnitConfigFile
     *
     * @param string $testsType
     */
    public static function testSelectPhpUnitConfigFile(string $testsType): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $expectedPhpUnitConfigFileName = self::getExpectedPhpUnitConfigFile($testsType);
        $dbgCtx->add(['expectedPhpUnitConfigFileName' => $expectedPhpUnitConfigFileName]);

        $command = 'php ' . '"' . SelectPhpUnitConfigFile::getFullPathToRunScript() . '"';
        $command .= ' ' . '--' . SelectPhpUnitConfigFile::TESTS_TYPE_CMD_LINE_OPT_NAME . '=' . $testsType;
        $dbgCtx->add(['command' => $command]);
        $outputLines = SelectPhpUnitConfigFile::execExternalCommand($command);
        self::assertCount(1, $outputLines);
        $actualPhpUnitConfigFileName = $outputLines[0];
        $dbgCtx->add(['actualPhpUnitConfigFileName' => $actualPhpUnitConfigFileName]);
        self::assertSame($expectedPhpUnitConfigFileName, $actualPhpUnitConfigFileName);
    }
}
