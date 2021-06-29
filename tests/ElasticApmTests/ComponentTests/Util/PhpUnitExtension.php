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

use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Impl\Util\TimeUtil;
use ElasticApmTests\Util\LogCategoryForTests;
use ElasticApmTests\Util\TestCaseBase;
use PHPUnit\Runner\AfterIncompleteTestHook;
use PHPUnit\Runner\AfterRiskyTestHook;
use PHPUnit\Runner\AfterSkippedTestHook;
use PHPUnit\Runner\AfterSuccessfulTestHook;
use PHPUnit\Runner\AfterTestErrorHook;
use PHPUnit\Runner\AfterTestFailureHook;
use PHPUnit\Runner\AfterTestWarningHook;
use PHPUnit\Runner\BeforeTestHook;

final class PhpUnitExtension implements
    BeforeTestHook,
    AfterSuccessfulTestHook,
    AfterTestFailureHook,
    AfterTestErrorHook,
    AfterTestWarningHook,
    AfterSkippedTestHook,
    AfterIncompleteTestHook,
    AfterRiskyTestHook
{
    /** @var string */
    public static $testEnvId;

    /** @var Logger */
    private $logger;

    public function __construct()
    {
        ComponentTestCaseBase::init();

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('appCodeHostKind', AppCodeHostKind::toString(AmbientContext::testConfig()->appCodeHostKind));
    }

    public function executeBeforeTest(string $test): void
    {
        self::$testEnvId = IdGenerator::generateId(/* idLengthInBytes */ 16);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Test starting...',
            [
                'test'                  => $test,
                'testEnvId'             => self::$testEnvId,
                'Environment variables' => getenv(),
            ]
        );

        TestCaseBase::printMessage('Test starting... (' . $test . ')');

        TestConfigUtil::assertAgentDisabled();
    }

    public static function formatTime(float $durationInSeconds): string
    {
        // Round to milliseconds
        $roundedDurationInSeconds = round($durationInSeconds, /* precision */ 3);
        return TimeFormatUtil::formatDurationInMicroseconds(TimeUtil::secondsToMicroseconds($roundedDurationInSeconds));
    }

    public function executeAfterSuccessfulTest(string $test, float $time): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Test finished successfully',
            ['test' => $test, 'time' => $time, 'testEnvId' => self::$testEnvId]
        );

        TestCaseBase::printMessage('Test finished successfully (' . $test . '). Time: ' . self::formatTime($time));
    }

    private function testFinishedUnsuccessfully(string $issue, string $test, string $message, float $time): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            "Test finished $issue",
            ['test' => $test, 'message' => $message, 'time' => $time, 'testEnvId' => self::$testEnvId]
        );

        TestCaseBase::printMessage(
            'Test finished ' . $issue . '(' . $test . ')'
            . '. Message: ' . $message . '. Time: ' . self::formatTime($time)
        );
    }

    public function executeAfterTestFailure(string $test, string $message, float $time): void
    {
        $this->testFinishedUnsuccessfully('with failure', $test, $message, $time);
    }

    public function executeAfterTestError(string $test, string $message, float $time): void
    {
        $this->testFinishedUnsuccessfully('with error', $test, $message, $time);
    }

    public function executeAfterTestWarning(string $test, string $message, float $time): void
    {
        $this->testFinishedUnsuccessfully('with warning', $test, $message, $time);
    }

    public function executeAfterSkippedTest(string $test, string $message, float $time): void
    {
        $this->testFinishedUnsuccessfully('as skipped', $test, $message, $time);
    }

    public function executeAfterIncompleteTest(string $test, string $message, float $time): void
    {
        $this->testFinishedUnsuccessfully('as incomplete', $test, $message, $time);
    }

    public function executeAfterRiskyTest(string $test, string $message, float $time): void
    {
        $this->testFinishedUnsuccessfully('as risky', $test, $message, $time);
    }
}
