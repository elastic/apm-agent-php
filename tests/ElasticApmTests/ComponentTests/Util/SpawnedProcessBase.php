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

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Closure;
use Elastic\Apm\Impl\Log\Level;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggingSubsystem;
use Elastic\Apm\Impl\Util\BoolUtil;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\UrlParts;
use ElasticApmTests\Util\LogCategoryForTests;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

abstract class SpawnedProcessBase implements LoggableInterface
{
    use LoggableTrait;

    public const FAILURE_PROCESS_EXIT_CODE = 234;
    public const DBG_PROCESS_NAME_ENV_VAR_NAME = 'DBG_PROCESS_NAME';

    /** @var Logger */
    private $logger;

    protected function __construct()
    {
        $this->logger = self::buildLogger()->addContext('this', $this);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Done',
            [
                'AmbientContext::testConfig()' => AmbientContextForTests::testConfig(),
                'Environment variables'        => EnvVarUtilForTests::getAll(),
            ]
        );
    }

    private static function buildLogger(): Logger
    {
        return AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );
    }

    /**
     * @return void
     */
    protected function processConfig(): void
    {
        self::getRequiredTestOption(AllComponentTestsOptionsMetadata::DATA_PER_PROCESS_OPTION_NAME);
        if ($this->shouldRegisterThisProcessWithResourcesCleaner()) {
            TestCase::assertNotNull(
                AmbientContextForTests::testConfig()->dataPerProcess->resourcesCleanerSpawnedProcessInternalId,
                LoggableToString::convert(AmbientContextForTests::testConfig())
            );
            TestCase::assertNotNull(
                AmbientContextForTests::testConfig()->dataPerProcess->resourcesCleanerPort,
                LoggableToString::convert(AmbientContextForTests::testConfig())
            );
        }
    }

    /**
     * @param Closure(SpawnedProcessBase): void $runImpl
     *
     * @throws Throwable
     */
    protected static function runSkeleton(Closure $runImpl): void
    {
        LoggingSubsystem::$isInTestingContext = true;

        try {
            $dbgProcessName = EnvVarUtilForTests::get(self::DBG_PROCESS_NAME_ENV_VAR_NAME);
            TestCase::assertIsString($dbgProcessName);
            AmbientContextForTests::init($dbgProcessName);
            $thisObj = new static(); // @phpstan-ignore-line

            if (!$thisObj->shouldAgentBeEnabled()) {
                ConfigUtilForTests::assertAgentDisabled();
            }

            $thisObj->processConfig();

            if ($thisObj->shouldRegisterThisProcessWithResourcesCleaner()) {
                $thisObj->registerWithResourcesCleaner();
            }

            $runImpl($thisObj);
        } catch (Throwable $throwable) {
            $level = Level::CRITICAL;
            $isFromAppCode = false;
            $throwableToLog = $throwable;
            if ($throwable instanceof WrappedAppCodeException) {
                $isFromAppCode = true;
                $level = Level::INFO;
                $throwableToLog = $throwable->wrappedException();
            }
            $logger = isset($thisObj) ? $thisObj->logger : self::buildLogger();
            ($loggerProxy = $logger->ifLevelEnabled($level, __LINE__, __FUNCTION__))
            && $loggerProxy->logThrowable(
                $throwableToLog,
                'Throwable escaped to the top of the script',
                ['isFromAppCode' => $isFromAppCode]
            );
            if ($isFromAppCode) {
                /** @noinspection PhpUnhandledExceptionInspection */
                throw $throwableToLog;
            } else {
                exit(self::FAILURE_PROCESS_EXIT_CODE);
            }
        }
    }

    /**
     * @param string $optName
     *
     * @return mixed
     */
    protected static function getRequiredTestOption(string $optName)
    {
        $optValue = AmbientContextForTests::testConfig()->getOptionValueByName($optName);
        if ($optValue === null) {
            $envVarName = ConfigUtilForTests::testOptionNameToEnvVarName($optName);
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    'Required configuration option is not set',
                    [
                        'optName'        => $optName,
                        'envVarName'     => $envVarName,
                        'dbgProcessName' => AmbientContextForTests::dbgProcessName(),
                        'testConfig'     => AmbientContextForTests::testConfig(),
                    ]
                )
            );
        }

        return $optValue;
    }

    protected function shouldAgentBeEnabled(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    protected function shouldRegisterThisProcessWithResourcesCleaner(): bool
    {
        return true;
    }

    protected function isThisProcessTestScoped(): bool
    {
        return false;
    }

    protected function registerWithResourcesCleaner(): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Registering with ' . ClassNameUtil::fqToShort(ResourcesCleaner::class) . '...'
        );

        TestCase::assertNotNull(AmbientContextForTests::testConfig()->dataPerProcess->resourcesCleanerPort);
        $resCleanerId = AmbientContextForTests::testConfig()->dataPerProcess->resourcesCleanerSpawnedProcessInternalId;
        TestCase::assertNotNull($resCleanerId);
        $response = HttpClientUtilForTests::sendRequest(
            HttpConstantsForTests::METHOD_POST,
            (new UrlParts())
                ->path(ResourcesCleaner::REGISTER_PROCESS_TO_TERMINATE_URI_PATH)
                ->port(AmbientContextForTests::testConfig()->dataPerProcess->resourcesCleanerPort),
            TestInfraDataPerRequest::withSpawnedProcessInternalId($resCleanerId),
            [
                ResourcesCleaner::PID_QUERY_HEADER_NAME => strval(getmypid()),
                ResourcesCleaner::IS_TEST_SCOPED_QUERY_HEADER_NAME
                                                        => BoolUtil::toString($this->isThisProcessTestScoped()),
            ]
        );
        if ($response->getStatusCode() !== HttpConstantsForTests::STATUS_OK) {
            throw new RuntimeException(
                'Failed to register with '
                . ClassNameUtil::fqToShort(ResourcesCleaner::class)
            );
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Successfully registered with ' . ClassNameUtil::fqToShort(ResourcesCleaner::class)
        );
    }
}
