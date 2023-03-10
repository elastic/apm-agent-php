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

namespace ElasticApmTests\Util;

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\ComponentTests\Util\AllComponentTestsOptionsMetadata;
use ElasticApmTests\ComponentTests\Util\AmbientContextForTests;
use ElasticApmTests\ComponentTests\Util\ConfigUtilForTests;
use ElasticApmTests\ComponentTests\Util\EnvVarUtilForTests;
use RuntimeException;

final class SelectPhpUnitConfigFile
{
    public const TESTS_TYPE_CMD_LINE_OPT_NAME = 'tests-type';
    public const ALL_CMD_LINE_OPT_NAMES = [self::TESTS_TYPE_CMD_LINE_OPT_NAME];
    public const ALL_MANDATORY_CMD_LINE_OPT_NAMES = self::ALL_CMD_LINE_OPT_NAMES;

    public const TESTS_TYPE_UNIT = 'unit';
    public const TESTS_TYPE_COMPONENT = 'component';
    public const ALL_TESTS_TYPES = [self::TESTS_TYPE_UNIT, self::TESTS_TYPE_COMPONENT];

    /** @var Logger */
    private $logger;

    public static function discoverPhpUnitMajorVersion(): int
    {
        return (new self())->discoverPhpUnitMajorVersionImpl();
    }

    public static function getFullPathToRunScript(): string
    {
        return FileUtilForTests::listToPath([__DIR__, 'runSelectPhpUnitConfigFile.php']);
    }

    /**
     * @param string $command
     *
     * @return string[]
     */
    public static function execExternalCommand(string $command): array
    {
        return (new self())->execExternalCommandImpl($command);
    }

    private static function initAmbientContextForTests(): void
    {
        $appCodeHostKindOptName = AllComponentTestsOptionsMetadata::APP_CODE_HOST_KIND_OPTION_NAME;
        $appCodeHostKindEnvVarName = ConfigUtilForTests::testOptionNameToEnvVarName($appCodeHostKindOptName);
        EnvVarUtilForTests::unset($appCodeHostKindEnvVarName);
        AmbientContextForTests::init(/* dbgProcessName */ ClassNameUtil::fqToShort(__CLASS__));
    }

    /**
     * @param string[] $cmdLineArgs
     *
     * @return string
     */
    public static function run(array $cmdLineArgs): string
    {
        self::initAmbientContextForTests();
        return (new self())->runImpl($cmdLineArgs);
    }

    private function __construct()
    {
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            'helper util' /* <- category */,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );
    }

    /**
     * @param string[] $cmdLineArgs
     *
     * @return string
     */
    public function runImpl(array $cmdLineArgs): string
    {
        $testsType = '';
        $this->parseCommandLine($cmdLineArgs, /* out */ $testsType);
        $phpUnitMajorVersion = $this->discoverPhpUnitMajorVersionImpl();
        return $this->selectConfigFile($testsType, $phpUnitMajorVersion);
    }

    /**
     * @param string               $msg
     * @param array<string, mixed> $ctx
     *
     * @throws RuntimeException
     *
     * @return never
     */
    private function fail(string $msg, array $ctx): void
    {
        ($loggerProxy = $this->logger->ifCriticalLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log($msg, $ctx);
        throw new RuntimeException($msg . '; ' . LoggableToString::convert($ctx));
    }

    private function extractCommandLineOptionValue(string $cmdLineArg, string $optName): ?string
    {
        $prefix = '--' . $optName . '=';
        if (!TextUtil::isPrefixOf($prefix, $cmdLineArg)) {
            return null;
        }
        $optValue = substr($cmdLineArg, /* offset */ strlen($prefix));
        if ($optValue === false) { // @phpstan-ignore-line
            $this->fail('substr failed', ['cmdLineArg' => $cmdLineArg, 'optName' => $optName]);
        }
        return $optValue;
    }

    /**
     * @param string[] $cmdLineArgs
     * @param string   $testsType
     */
    private function parseCommandLine(array $cmdLineArgs, string &$testsType): void
    {
        $optNameToValue = [];
        $optNameToValue[self::TESTS_TYPE_CMD_LINE_OPT_NAME] =& $testsType;

        $isFirstArg = true;
        foreach ($cmdLineArgs as $cmdLineArg) {
            if ($isFirstArg) {
                $isFirstArg = false;
                continue;
            }

            $isKnownOption = false;
            foreach (self::ALL_CMD_LINE_OPT_NAMES as $optName) {
                if (($optValue = $this->extractCommandLineOptionValue($cmdLineArg, $optName)) !== null) {
                    $optNameToValue[$optName] = $optValue;
                    $isKnownOption = true;
                    break;
                }
            }
            if (!$isKnownOption) {
                $this->fail(
                    'Unknown command line option',
                    ['cmdLineArg' => $cmdLineArg, 'cmdLineArgs' => $cmdLineArgs]
                );
            }
        }

        foreach (self::ALL_MANDATORY_CMD_LINE_OPT_NAMES as $optName) {
            if (!array_key_exists($optName, $optNameToValue)) {
                $this->fail(
                    'Mandatory command line option is missing',
                    ['optName' => $optName, 'cmdLineArgs' => $cmdLineArgs, 'parsed args' => $optNameToValue]
                );
            }
        }
    }

    private function discoverPhpUnitMajorVersionImpl(): int
    {
        $command = './vendor/bin/phpunit --version';
        $dbgCtx = ['command' => $command];
        $output = $this->execExternalCommand($command);
        ArrayUtilForTests::append(['output' => $output], /* ref */ $dbgCtx);

        // $ ./vendor/bin/phpunit --version
        // PHPUnit 9.6.4 by Sebastian Bergmann and contributors.
        //
        // $

        $strBeforeVersion = 'PHPUnit ';
        ArrayUtilForTests::append(['strBeforeVersion' => $strBeforeVersion], /* ref */ $dbgCtx);
        foreach ($output as $outputLine) {
            ArrayUtilForTests::append(['outputLine' => $outputLine], /* ref */ $dbgCtx);
            $strBeforeVersionPos = strpos($outputLine, $strBeforeVersion);
            if (!is_int($strBeforeVersionPos)) {
                continue;
            }
            $outputPartStartingWithVersion = substr($outputLine, $strBeforeVersionPos + strlen($strBeforeVersion));
            // Limit to 2 parts since we are only interested in MAJOR part of the version
            $partsAsStrings = explode(/* separator */ '.', $outputPartStartingWithVersion, /* limit */ 2);
            ArrayUtilForTests::append(['partsAsStrings' => $partsAsStrings], /* ref */ $dbgCtx);
            if ((!is_array($partsAsStrings)) || (count($partsAsStrings) < 2)) {
                $this->fail('Failed to separate MAJOR part of the version', $dbgCtx);
            }
            $majorPartAsString = $partsAsStrings[0];
            ArrayUtilForTests::append(['majorPartAsString' => $majorPartAsString], /* ref */ $dbgCtx);
            if (filter_var($majorPartAsString, FILTER_VALIDATE_INT) === false) {
                $this->fail('MAJOR part of the version is not a valid integer', $dbgCtx);
            }
            return intval($majorPartAsString);
        }

        $this->fail('Output does not contain expected part (`' . $strBeforeVersion . '\')', $dbgCtx);
    }

    /**
     * @param string $command
     *
     * @return string[]
     */
    private function execExternalCommandImpl(string $command): array
    {
        $dbgCtx = ['command' => $command];
        /** @var string[] $output */
        $output = [];
        $exitCode = 0;
        exec($command, /* out */ $output, /* out */ $exitCode);
        ArrayUtilForTests::append(['output' => $output, 'exitCode' => $exitCode], /* ref */ $dbgCtx);
        if ($exitCode !== 0) {
            $this->fail('Command exit code signals a failutre', $dbgCtx);
        }
        return $output;
    }

    private function buildConfigFileName(string $testsType, ?int $phpUnitMajorVersion = null): string
    {
        $suffix = $phpUnitMajorVersion === null ? '' : ('_v' . $phpUnitMajorVersion . '_format');
        switch ($testsType) {
            case self::TESTS_TYPE_UNIT:
                return 'phpunit' . $suffix . '.xml' . ($phpUnitMajorVersion === null ? '.dist' : '');
            case self::TESTS_TYPE_COMPONENT:
                return 'phpunit_component_tests' . $suffix . '.xml';
            default:
                $this->fail('Unknown tests type', ['testsType' => $testsType]);
        }
    }

    private function selectConfigFile(string $testsType, int $phpUnitMajorVersion): string
    {
        $listOfFilesInRepoRootDir = $this->execExternalCommand(/* command */ 'ls -1');
        $configForFileNamePhpUnitMajorVersion = self::buildConfigFileName($testsType, $phpUnitMajorVersion);
        if (in_array($configForFileNamePhpUnitMajorVersion, $listOfFilesInRepoRootDir)) {
            return $configForFileNamePhpUnitMajorVersion;
        }
        return self::buildConfigFileName($testsType);
    }
}
