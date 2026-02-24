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

namespace ElasticApmTests\ComponentTests;

use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\ConfigUtilForTests;
use ElasticApmTests\TestsRootDir;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\FileUtilForTests;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class GenerateUnpackScriptsTest extends ComponentTestCaseBase implements LoggableInterface
{
    use LoggableTrait;

    private const PHP_VERSION_KEY = 'PHP_VERSION';
    private const LINUX_PACKAGE_TYPE_KEY = 'LINUX_PACKAGE_TYPE';
    private const TESTING_TYPE_KEY = 'TESTING_TYPE';
    private const APP_CODE_HOST_KIND_ENV_VAR_NAME = 'ELASTIC_APM_PHP_TESTS_APP_CODE_HOST_KIND';
    private const TESTS_GROUP_ENV_VAR_NAME = 'ELASTIC_APM_PHP_TESTS_GROUP';

    private const PHP_VERSION_7_4 = '7.4';
    // Make sure list of PHP versions supported by the Elastic APM PHP Agent is in sync.
    // See the comment in .ci/shared.sh
    private const SUPPORTED_PHP_VERSIONS = ['7.2', '7.3', self::PHP_VERSION_7_4, '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];

    private const LINUX_PACKAGE_TYPE_DEB = 'deb';
    private const LINUX_PACKAGE_TYPE_RPM = 'rpm';
    private const LINUX_PACKAGE_TYPE_TAR = 'tar';
    private const LINUX_NATIVE_PACKAGE_TYPES = ['apk', self::LINUX_PACKAGE_TYPE_DEB, self::LINUX_PACKAGE_TYPE_RPM];
    private const LINUX_PACKAGE_TYPES = ['apk', self::LINUX_PACKAGE_TYPE_DEB, self::LINUX_PACKAGE_TYPE_RPM, 'tar'];

    private const APP_CODE_HOST_KIND_ALL = 'all';
    private const APP_CODE_HOST_LEAF_KINDS = ['Builtin_HTTP_server', 'CLI_script'];

    private const TESTS_GROUP_SMOKE = 'smoke';
    private const TESTS_LEAF_GROUPS = ['does_not_require_external_services', 'requires_external_services'];

    private const AGENT_UPGRADE_TESTING_TYPE = 'agent-upgrade';
    private const LIFECYCLE_TESTING_TYPE = 'lifecycle';
    private const PHP_UPGRADE_TESTING_TYPE = 'php-upgrade';
    private const SUPPORTED_TESTING_TYPES
        = [
            self::AGENT_UPGRADE_TESTING_TYPE,
            self::LIFECYCLE_TESTING_TYPE,
            'lifecycle-apache',
            'lifecycle-fpm',
            self::PHP_UPGRADE_TESTING_TYPE,
        ];

    /** @var string */
    private $unpackAndPrintEnvVarsScriptFullPath;

    /** @var array<string, array<string, mixed>> */
    private $matrixRowToExpectedEnvVars;

    private static function agentSyslogLevelEnvVarName(): string
    {
        return ConfigUtilForTests::agentOptionNameToEnvVarName(OptionNames::LOG_LEVEL_SYSLOG);
    }

    /**
     * @param string $command
     *
     * @return string[]
     */
    private static function execCommand(string $command): array
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $outputLastLine = exec($command, /* out */ $outputLinesAsArray, /* out */ $exitCode);
        $dbgCtx->add(['exitCode' => $exitCode, 'outputLinesAsArray' => $outputLinesAsArray, 'outputLastLine' => $outputLastLine]);
        self::assertSame(0, $exitCode);
        self::assertIsString($outputLastLine);
        self::assertIsArray($outputLinesAsArray);
        return $outputLinesAsArray;
    }

    private static function convertAppHostKindShortToLongName(string $shortName): string
    {
        switch ($shortName) {
            case 'all':
                return 'all';
            case 'cli':
                return 'CLI_script';
            case 'http':
                return 'Builtin_HTTP_server';
            default:
                self::fail($shortName);
        }
    }

    private static function convertTestsGroupShortToLongName(string $shortName): string
    {
        switch ($shortName) {
            case 'no_ext_svc':
                return 'does_not_require_external_services';
            case 'smoke':
                return 'smoke';
            case 'with_ext_svc':
                return 'requires_external_services';
            default:
                self::fail($shortName);
        }
    }

    /**
     * @param string               $matrixRow
     * @param array<string, mixed> $expectedEnvVars
     */
    private function execUnpackAndAssert(string $matrixRow, array $expectedEnvVars): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $cmd = $this->unpackAndPrintEnvVarsScriptFullPath . ' ' . $matrixRow;
        $actualEnvVarNameValueLines = self::execCommand($cmd);
        self::assertNotEmpty($actualEnvVarNameValueLines);
        $actualEnvVars = [];
        foreach ($actualEnvVarNameValueLines as $actualEnvVarNameValueLine) {
            $actualEnvVarNameValue = explode('=', $actualEnvVarNameValueLine, /* limit */ 2);
            $actualEnvVars[$actualEnvVarNameValue[0]] = $actualEnvVarNameValue[1];
        }
        $dbgCtx->add(['actualEnvVarNameValueLines' => $actualEnvVarNameValueLines, 'actualEnvVars' => $actualEnvVars]);

        $elasticExpectedEnvVars = array_filter(
            $expectedEnvVars,
            function (string $envVarName): bool {
                return TextUtil::isPrefixOf('ELASTIC_', $envVarName);
            },
            ARRAY_FILTER_USE_KEY
        );
        self::assertMapIsSubsetOf($elasticExpectedEnvVars, $actualEnvVars);
    }

    /**
     * @param string $matrixRow
     *
     * @return array<string, mixed>
     */
    private static function unpackRowToEnvVars(string $matrixRow): array
    {
        /*
         * Expected format (see generate_package_lifecycle_test_matrix.sh)
         *
         *      phpVersion,linuxPackageType,testingType,appHostKindShortName,testsGroupShortName[,optionalTail]
         *      [0]        [1]              [2]         [3]                  [4]                  [5], [6] ...
         */

        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $matrixRowParts = explode(',', $matrixRow);
        $dbgCtx->add(['matrixRowParts' => $matrixRowParts]);
        self::assertGreaterThanOrEqual(3, count($matrixRowParts));

        $result = [];

        $phpVersion = $matrixRowParts[0];
        self::assertContainsEx($phpVersion, self::SUPPORTED_PHP_VERSIONS);
        ArrayUtilForTests::addUnique(self::PHP_VERSION_KEY, $phpVersion, /* ref */ $result);

        $linuxPackageType = $matrixRowParts[1];
        self::assertContainsEx($linuxPackageType, self::LINUX_PACKAGE_TYPES);
        ArrayUtilForTests::addUnique(self::LINUX_PACKAGE_TYPE_KEY, $linuxPackageType, /* ref */ $result);

        $testingType = $matrixRowParts[2];
        self::assertContainsEx($testingType, self::SUPPORTED_TESTING_TYPES);
        ArrayUtilForTests::addUnique(self::TESTING_TYPE_KEY, $testingType, /* ref */ $result);

        if (count($matrixRowParts) === 3) {
            return $result;
        }

        $appHostKindShortName = $matrixRowParts[3];
        ArrayUtilForTests::addUnique(self::APP_CODE_HOST_KIND_ENV_VAR_NAME, self::convertAppHostKindShortToLongName($appHostKindShortName), /* ref */ $result);

        $testsGroupShortName = $matrixRowParts[4];
        ArrayUtilForTests::addUnique(self::TESTS_GROUP_ENV_VAR_NAME, self::convertTestsGroupShortToLongName($testsGroupShortName), /* ref */ $result);

        $matrixRowOptionalParts = array_slice($matrixRowParts, 5);
        foreach ($matrixRowOptionalParts as $optionalPart) {
            $keyValue = explode('=', $optionalPart);
            self::unpackRowOptionalPartsToEnvVars($keyValue[0], $keyValue[1], /* ref */ $result);
        }

        return $result;
    }

    /**
     * @param string               $key
     * @param string               $value
     * @param array<string, mixed> $result
     */
    private static function unpackRowOptionalPartsToEnvVars(string $key, string $value, array &$result): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        switch ($key) {
            case 'agent_syslog_level':
                ArrayUtilForTests::addUnique(self::agentSyslogLevelEnvVarName(), $value, /* ref */ $result);
                break;
            default:
                $dbgCtx->add(['key' => $key, 'value' => $value]);
                self::fail('Unexpected key');
        }
    }

    /**
     * @param string[] $matrixRows
     *
     * @return array<string, array<string, mixed>>
     */
    private static function unpackToEnvVars(array $matrixRows): array
    {
        $result = [];
        foreach ($matrixRows as $matrixRow) {
            ArrayUtilForTests::addUnique($matrixRow, self::unpackRowToEnvVars($matrixRow), /* ref */ $result);
        }
        self::assertSame(count($matrixRows), count($result));
        return $result;
    }

    /**
     * @param array<string, mixed> $whereEnvVars
     * @param array<string, mixed> $envVars
     *
     * @return bool
     */
    private static function doesMatchWhere(array $whereEnvVars, array $envVars): bool
    {
        foreach ($whereEnvVars as $whereEnvVarName => $whereEnvVarVal) {
            if (!array_key_exists($whereEnvVarName, $envVars) || $whereEnvVarVal !== $envVars[$whereEnvVarName]) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string, mixed>                $whereEnvVars
     * @param array<string, array<string, mixed>> $matrixRowToEnvVars
     *
     * @return array<string, array<string, mixed>>
     */
    private static function select(array $whereEnvVars, array $matrixRowToEnvVars): array
    {
        $result = [];
        foreach ($matrixRowToEnvVars as $matrixRow => $envVars) {
            if (!self::doesMatchWhere($whereEnvVars, $envVars)) {
                continue;
            }
            ArrayUtilForTests::addUnique($matrixRow, $envVars, /* ref */ $result);
        }
        return $result;
    }

    private static function earliestSupportedPhpVersion(): string
    {
        return ArrayUtilForTests::getFirstValue(self::SUPPORTED_PHP_VERSIONS);
    }

    private static function latestSupportedPhpVersion(): string
    {
        return ArrayUtilForTests::getLastValue(self::SUPPORTED_PHP_VERSIONS);
    }

    private function assertSufficientCoverageLifecycleWithIncreasedLogLevel(): void
    {
        $assertForPhpVersionAndLogLevel = function (string $phpVersion, int $logLevel): void {
            AssertMessageStack::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], AssertMessageStack::funcArgs()));
            foreach (self::APP_CODE_HOST_LEAF_KINDS as $appHostKind) {
                $dbgCtx->pushSubScope();
                foreach (self::TESTS_LEAF_GROUPS as $testsGroup) {
                    $whereEnvVars = [
                        self::PHP_VERSION_KEY                 => $phpVersion,
                        self::TESTING_TYPE_KEY                => self::LIFECYCLE_TESTING_TYPE,
                        self::APP_CODE_HOST_KIND_ENV_VAR_NAME => $appHostKind,
                        self::TESTS_GROUP_ENV_VAR_NAME        => $testsGroup,
                        self::agentSyslogLevelEnvVarName()    => LogLevel::intToName($logLevel),
                    ];
                    $dbgCtx->clearCurrentSubScope(['whereEnvVars' => $whereEnvVars]);
                    $selectedMatrixRowToExpectedEnvVars = self::select($whereEnvVars, $this->matrixRowToExpectedEnvVars);
                    self::assertNotEmpty($selectedMatrixRowToExpectedEnvVars);
                }
                $dbgCtx->popSubScope();
            }
        };

        $assertForPhpVersionAndLogLevel(self::earliestSupportedPhpVersion(), LogLevel::DEBUG);
        $assertForPhpVersionAndLogLevel(self::latestSupportedPhpVersion(), LogLevel::TRACE);
    }

    private function assertSufficientCoverageLifecycleTarPackage(): void
    {
        foreach (self::SUPPORTED_PHP_VERSIONS as $phpVersion) {
            $this->assertAllTestsAreSmoke(
                [
                    self::PHP_VERSION_KEY        => $phpVersion,
                    self::LINUX_PACKAGE_TYPE_KEY => self::LINUX_PACKAGE_TYPE_TAR,
                    self::TESTING_TYPE_KEY       => self::LIFECYCLE_TESTING_TYPE,
                ]
            );
        }
    }

    private function assertSufficientCoverageLifecycle(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, ['this' => $this]);
        foreach (self::SUPPORTED_PHP_VERSIONS as $phpVersion) {
            $dbgCtx->add(['phpVersion' => $phpVersion]);
            foreach (self::LINUX_NATIVE_PACKAGE_TYPES as $linuxPackageType) {
                $dbgCtx->add(['linuxPackageType' => $linuxPackageType]);
                $whereEnvVarsLifecycle = [self::PHP_VERSION_KEY => $phpVersion, self::LINUX_PACKAGE_TYPE_KEY => $linuxPackageType, self::TESTING_TYPE_KEY => self::LIFECYCLE_TESTING_TYPE];
                $this->assertAllTestsAreLeaf($whereEnvVarsLifecycle);
                foreach (self::APP_CODE_HOST_LEAF_KINDS as $appHostKind) {
                    $dbgCtx->add(['appHostKind' => $appHostKind]);
                    foreach (self::TESTS_LEAF_GROUPS as $testsGroup) {
                        $dbgCtx->add(['testsGroup' => $testsGroup]);
                        $whereEnvVars = array_merge($whereEnvVarsLifecycle, [self::APP_CODE_HOST_KIND_ENV_VAR_NAME => $appHostKind, self::TESTS_GROUP_ENV_VAR_NAME => $testsGroup]);
                        $dbgCtx->add(['whereEnvVars' => $whereEnvVars]);
                        $selectedMatrixRowToExpectedEnvVars = self::select($whereEnvVars, $this->matrixRowToExpectedEnvVars);
                        self::assertNotEmpty($selectedMatrixRowToExpectedEnvVars);
                    }
                }
            }
        }

        self::assertSufficientCoverageLifecycleWithIncreasedLogLevel();
        self::assertSufficientCoverageLifecycleTarPackage();
    }

    /**
     * @param array<string, mixed> $whereEnvVars
     */
    private function assertAllTestsAreSmoke(array $whereEnvVars): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], AssertMessageStack::funcArgs()));
        $variants = self::select($whereEnvVars, $this->matrixRowToExpectedEnvVars);
        $dbgCtx->add(['variants' => $variants]);
        self::assertNotEmpty($variants);
        $dbgCtx->pushSubScope();
        foreach ($variants as $variant) {
            $dbgCtx->clearCurrentSubScope(['variant' => $variant]);
            self::assertSame(self::APP_CODE_HOST_KIND_ALL, $variant[self::APP_CODE_HOST_KIND_ENV_VAR_NAME]);
            self::assertSame(self::TESTS_GROUP_SMOKE, $variant[self::TESTS_GROUP_ENV_VAR_NAME]);
        }
        $dbgCtx->popSubScope();
    }

    /**
     * @param array<string, mixed> $whereEnvVars
     */
    private function assertAllTestsAreLeaf(array $whereEnvVars): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], AssertMessageStack::funcArgs()));
        $variants = self::select($whereEnvVars, $this->matrixRowToExpectedEnvVars);
        $dbgCtx->add(['variants' => $variants]);
        self::assertNotEmpty($variants);
        $dbgCtx->pushSubScope();
        foreach ($variants as $variant) {
            $dbgCtx->clearCurrentSubScope(['variant' => $variant]);
            self::assertContainsEx($variant[self::APP_CODE_HOST_KIND_ENV_VAR_NAME], self::APP_CODE_HOST_LEAF_KINDS);
            self::assertContainsEx($variant[self::TESTS_GROUP_ENV_VAR_NAME], self::TESTS_LEAF_GROUPS);
        }
        $dbgCtx->popSubScope();
    }

    private function assertSufficientCoverageAgentUpgrade(): void
    {
        /** @noinspection PhpForeachOverSingleElementArrayLiteralInspection */
        foreach ([self::PHP_VERSION_7_4] as $phpVersion) {
            /** @noinspection PhpForeachOverSingleElementArrayLiteralInspection */
            foreach ([self::LINUX_PACKAGE_TYPE_RPM] as $linuxPackageType) {
                $this->assertAllTestsAreSmoke(
                    [
                        self::PHP_VERSION_KEY        => $phpVersion,
                        self::LINUX_PACKAGE_TYPE_KEY => $linuxPackageType,
                        self::TESTING_TYPE_KEY       => self::AGENT_UPGRADE_TESTING_TYPE,
                    ]
                );
            }
        }
        foreach ([self::PHP_VERSION_7_4, self::latestSupportedPhpVersion()] as $phpVersion) {
            /** @noinspection PhpForeachOverSingleElementArrayLiteralInspection */
            foreach ([self::LINUX_PACKAGE_TYPE_DEB] as $linuxPackageType) {
                $this->assertAllTestsAreSmoke(
                    [
                        self::PHP_VERSION_KEY        => $phpVersion,
                        self::LINUX_PACKAGE_TYPE_KEY => $linuxPackageType,
                        self::TESTING_TYPE_KEY       => self::AGENT_UPGRADE_TESTING_TYPE,
                    ]
                );
            }
        }
    }

    private function assertSufficientCoveragePhpUpgrade(): void
    {
        $this->assertAllTestsAreSmoke(
            [
                self::PHP_VERSION_KEY        => self::earliestSupportedPhpVersion(),
                self::LINUX_PACKAGE_TYPE_KEY => self::LINUX_PACKAGE_TYPE_RPM,
                self::TESTING_TYPE_KEY       => self::PHP_UPGRADE_TESTING_TYPE,
            ]
        );
    }

    private function assertSufficientCoverage(): void
    {
        self::assertSufficientCoverageAgentUpgrade();
        self::assertSufficientCoverageLifecycle();
        self::assertSufficientCoveragePhpUpgrade();
    }

    public function testGenerateAndUnpackAreInSync(): void
    {
        $repoRootFullPath = FileUtilForTests::normalizePath(
            FileUtilForTests::listToPath([TestsRootDir::$fullPath, '..'])
        );

        $generateScriptFullPath = FileUtilForTests::listToPath(
            [$repoRootFullPath, '.ci', 'generate_package_lifecycle_test_matrix.sh']
        );
        self::assertFileExists($generateScriptFullPath);

        $this->unpackAndPrintEnvVarsScriptFullPath = FileUtilForTests::listToPath(
            [TestsRootDir::$fullPath, 'tools', 'unpack_package_lifecycle_test_matrix_row_and_print_env_vars.sh']
        );
        self::assertFileExists($this->unpackAndPrintEnvVarsScriptFullPath);

        $outputLinesAsArray = self::execCommand($generateScriptFullPath);
        self::assertNotEmpty($outputLinesAsArray);
        $this->matrixRowToExpectedEnvVars = self::unpackToEnvVars($outputLinesAsArray);

        $this->assertSufficientCoverage();

        foreach ($this->matrixRowToExpectedEnvVars as $matrixRow => $expectedEnvVars) {
            $this->execUnpackAndAssert($matrixRow, $expectedEnvVars);
        }
    }
}
