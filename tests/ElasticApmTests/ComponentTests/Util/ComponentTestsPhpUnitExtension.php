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

/**
 * PhpUnitExtension is used in phpunit_component_tests.xml
 *
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\NoopTracer;
use Elastic\Apm\Impl\Util\TimeUtil;
use ElasticApmTests\Util\LogCategoryForTests;
use ElasticApmTests\Util\PhpUnitExtensionBase;
use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\AfterIncompleteTestHook;
use PHPUnit\Runner\AfterRiskyTestHook;
use PHPUnit\Runner\AfterSkippedTestHook;
use PHPUnit\Runner\AfterSuccessfulTestHook;
use PHPUnit\Runner\AfterTestErrorHook;
use PHPUnit\Runner\AfterTestFailureHook;
use PHPUnit\Runner\AfterTestWarningHook;
use PHPUnit\Runner\BeforeTestHook;

/**
 * Referenced in PHPUnit's configuration file - phpunit_component_tests.xml
 */
final class ComponentTestsPhpUnitExtension extends PhpUnitExtensionBase implements
    BeforeTestHook,
    AfterSuccessfulTestHook,
    AfterTestFailureHook,
    AfterTestErrorHook,
    AfterTestWarningHook,
    AfterSkippedTestHook,
    AfterIncompleteTestHook,
    AfterRiskyTestHook
{
    private const DBG_PROCESS_NAME = 'Component tests';

    /** @var Logger */
    private $logger;

    /** @var GlobalTestInfra */
    private static $globalTestInfra = null;

    public function __construct()
    {
        parent::__construct(self::DBG_PROCESS_NAME);

        // We spin off test infrastructure servers here and not on demand
        // in self::getGlobalTestInfra() because PHPUnit might fork to run individual tests
        // and ResourcesCleaner would track the PHPUnit child process as its master which would be wrong
        self::$globalTestInfra = new GlobalTestInfra();

        GlobalTracerHolder::setValue(NoopTracer::singletonInstance());

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('appCodeHostKind', AmbientContextForTests::testConfig()->appCodeHostKind());
    }

    public function __destruct()
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Destroying...');

        self::$globalTestInfra->getResourcesCleaner()->signalAndWaitForItToExit();
    }

    public static function getGlobalTestInfra(): GlobalTestInfra
    {
        return self::$globalTestInfra;
    }

    public static function initSingletons(): void
    {
        AmbientContextForTests::init(self::DBG_PROCESS_NAME);
    }

    public function executeBeforeTest(string $test): void
    {
        parent::executeBeforeTest($test);

        if (($runBeforeEachTest = AmbientContextForTests::testConfig()->runBeforeEachTest) !== null) {
            $exitCode = ProcessUtilForTests::startProcessAndWaitUntilExit(
                $runBeforeEachTest,
                EnvVarUtilForTests::getAll() /* <- envVars */,
                true /* <- shouldCaptureStdOutErr */,
                0 /* <- expectedExitCode */
            );
            TestCase::assertSame(0, $exitCode);
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Test starting...',
            ['test' => $test, 'Environment variables' => EnvVarUtilForTests::getAll()]
        );

        ConfigUtilForTests::assertAgentDisabled();
    }

    public static function formatTime(float $durationInSeconds): string
    {
        // Round to milliseconds
        $roundedDurationInSeconds = round($durationInSeconds, /* precision */ 3);
        return TimeUtil::formatDurationInMicroseconds(TimeUtil::secondsToMicroseconds($roundedDurationInSeconds));
    }

    public function executeAfterSuccessfulTest(string $test, /* test duration in seconds */ float $time): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Test finished successfully', ['test' => $test, 'duration' => self::formatTime($time)]);
    }

    private function testFailed(string $issue, string $test, string $message, float $time): void
    {
        ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            "Test finished $issue",
            [
                'test'              => $test,
                'message'           => $message,
                'duration'          => self::formatTime($time),
            ]
        );
    }

    public function executeAfterTestFailure(string $test, string $message, float $time): void
    {
        $this->testFailed('with failure', $test, $message, $time);
    }

    public function executeAfterTestError(string $test, string $message, float $time): void
    {
        $this->testFailed('with error', $test, $message, $time);
    }

    public function executeAfterTestWarning(string $test, string $message, float $time): void
    {
        $this->testFailed('with warning', $test, $message, $time);
    }

    public function executeAfterSkippedTest(string $test, string $message, float $time): void
    {
        $this->testFailed('as skipped', $test, $message, $time);
    }

    public function executeAfterIncompleteTest(string $test, string $message, float $time): void
    {
        $this->testFailed('as incomplete', $test, $message, $time);
    }

    public function executeAfterRiskyTest(string $test, string $message, float $time): void
    {
        $this->testFailed('as risky', $test, $message, $time);
    }
}
