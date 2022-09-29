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

use Elastic\Apm\Impl\Log\LoggableToString;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\TestsRootDir;
use ElasticApmTests\Util\FileUtilForTests;

/**
 * @group does_not_require_external_services
 */
final class GenerateUnpackScriptsTest extends ComponentTestCaseBase
{
    /**
     * @param string $command
     *
     * @return string[]
     */
    private static function execCommand(string $command): array
    {
        $outputLastLine = exec($command, /* out */ $outputLinesAsArray, /* out */ $exitCode);
        $ctx = LoggableToString::convert(
            [
                'command' => $command,
                'exitCode' => $exitCode,
                'outputLinesAsArray' => $outputLinesAsArray,
                'outputLastLine' => $outputLastLine
            ]
        );
        self::assertSame(0, $exitCode, $ctx);
        self::assertIsString($outputLastLine, $ctx);
        self::assertIsArray($outputLinesAsArray, $ctx);
        return $outputLinesAsArray;
    }

    private static function convertComponentTestsAppHostKindShortNameToLongName(string $shortName): string
    {
        switch ($shortName) {
            case 'cli':
                return 'CLI_script';
            case 'http':
                return 'Builtin_HTTP_server';
            default:
                self::fail($shortName);
        }
    }

    private static function convertComponentTestsGroupShortNameToLongName(string $shortName): string
    {
        switch ($shortName) {
            case 'no_ext_svc':
                return 'does_not_require_external_services';
            case 'with_ext_svc':
                return 'requires_external_services';
            default:
                self::fail($shortName);
        }
    }

    private static function execUnpackAndAssert(
        string $unpackAndPrintEnvVarsScriptFullPath,
        string $generatedMatrixRow
    ): void {
        $cmd = $unpackAndPrintEnvVarsScriptFullPath . ' ' . $generatedMatrixRow;
        $envVarNameValueLines = self::execCommand($cmd);
        self::assertNotEmpty($envVarNameValueLines);
        $envVars = [];
        foreach ($envVarNameValueLines as $envVarNameValueLine) {
            $envVarNameValue = explode('=', $envVarNameValueLine, /* limit */ 2);
            $envVars[$envVarNameValue[0]] = $envVarNameValue[1];
        }
        /*
         * Expected format (see generate_package_lifecycle_test_matrix.sh)
         *
         *      phpVersion,linuxPackageType,testingType,componentTestsAppHostKindShortName,componentTestsGroup
         *      [0]        [1]              [2]         [3]                                [4]
         */
        $generatedMatrixRowParts = explode(',', $generatedMatrixRow);
        self::assertCount(5, $generatedMatrixRowParts);
        $ctx = LoggableToString::convert(
            [
                'generatedMatrixRow' => $generatedMatrixRow,
                'generatedMatrixRowParts' => $generatedMatrixRowParts,
                'envVarNameValueLines' => $envVarNameValueLines,
                'envVars' => $envVars,
            ]
        );
        $appHostKindLongName = self::convertComponentTestsAppHostKindShortNameToLongName($generatedMatrixRowParts[3]);
        self::assertSame($appHostKindLongName, $envVars['ELASTIC_APM_PHP_TESTS_APP_CODE_HOST_KIND'], $ctx);
        $testsGroupLongName = self::convertComponentTestsGroupShortNameToLongName($generatedMatrixRowParts[4]);
        self::assertSame($testsGroupLongName, $envVars['ELASTIC_APM_PHP_TESTS_GROUP'], $ctx);
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

        $unpackAndPrintEnvVarsScriptFullPath = FileUtilForTests::listToPath(
            [TestsRootDir::$fullPath, 'tools', 'unpack_package_lifecycle_test_matrix_row_and_print_env_vars.sh']
        );
        self::assertFileExists($generateScriptFullPath);

        $outputLinesAsArray = self::execCommand($generateScriptFullPath);
        self::assertNotEmpty($outputLinesAsArray);
        foreach ($outputLinesAsArray as $generatedMatrixRow) {
            self::execUnpackAndAssert($unpackAndPrintEnvVarsScriptFullPath, $generatedMatrixRow);
        }
    }
}
