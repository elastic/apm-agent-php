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

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ElasticApmExtensionUtil;
use ElasticApmTests\Util\LogCategoryForTests;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

abstract class AppCodeHostBase extends SpawnedProcessBase
{
    /** @var Logger */
    private $logger;

    public function __construct()
    {
        if (!ElasticApmExtensionUtil::isLoaded()) {
            throw new RuntimeException(
                'Environment hosting component tests application code should have '
                . ElasticApmExtensionUtil::EXTENSION_NAME . ' extension loaded.'
                . ' php_ini_loaded_file(): ' . php_ini_loaded_file() . '.'
            );
        }

        parent::__construct();

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Done', ['Environment variables' => getenv()]);
    }

    protected function shouldAgentBeEnabled(): bool
    {
        return true;
    }

    abstract protected function runImpl(): void;

    public static function run(?string &$topLevelCodeId): void
    {
        self::runSkeleton(
            function (SpawnedProcessBase $thisObjArg) use (&$topLevelCodeId): void {
                $topLevelCodeId = AmbientContext::testConfig()->sharedDataPerRequest->appTopLevelCodeId;
                if (!is_null($topLevelCodeId)) {
                    return;
                }

                /** var AppCodeHostBase */
                $thisObj = $thisObjArg;
                $thisObj->runImpl(); // @phpstan-ignore-line
            }
        );
    }

    protected function registerWithResourcesCleaner(): void
    {
        // We don't want any of the infrastructure operations to be recorded as application's APM events
        ElasticApm::pauseRecording();

        try {
            parent::registerWithResourcesCleaner();
        } finally {
            ElasticApm::resumeRecording();
        }
    }

    protected function callAppCode(): void
    {
        $logCtx = [
            'appCodeClass'     => AmbientContext::testConfig()->sharedDataPerRequest->appCodeClass,
            'appCodeMethod'    => AmbientContext::testConfig()->sharedDataPerRequest->appCodeMethod,
            'appCodeArguments' => AmbientContext::testConfig()->sharedDataPerRequest->appCodeArguments,
            'agentEphemeralId' => AmbientContext::testConfig()->sharedDataPerRequest->agentEphemeralId,
        ];

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Calling application code...', $logCtx);

        $msg = LoggableToString::convert(AmbientContext::testConfig());
        TestCase::assertNotNull(AmbientContext::testConfig()->sharedDataPerRequest->appCodeClass, $msg);
        TestCase::assertNotNull(AmbientContext::testConfig()->sharedDataPerRequest->appCodeMethod, $msg);

        self::setAgentEphemeralId();

        try {
            $methodToCall = [
                AmbientContext::testConfig()->sharedDataPerRequest->appCodeClass,
                AmbientContext::testConfig()->sharedDataPerRequest->appCodeMethod,
            ];
            if (is_null(AmbientContext::testConfig()->sharedDataPerRequest->appCodeArguments)) {
                /** @phpstan-ignore-next-line */
                call_user_func($methodToCall);
            } else {
                /** @phpstan-ignore-next-line */
                call_user_func($methodToCall, AmbientContext::testConfig()->sharedDataPerRequest->appCodeArguments);
            }
        } catch (Throwable $throwable) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->logThrowable($throwable, 'Call to application code exited by exception', $logCtx);
            throw new WrappedAppCodeException($throwable);
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Call to application code completed', $logCtx);
    }

    public static function setAgentEphemeralId(): void
    {
        $logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );

        $msg = LoggableToString::convert(AmbientContext::testConfig());
        /** @phpstan-ignore-next-line */
        TestCase::assertTrue(isset(AmbientContext::testConfig()->sharedDataPerRequest->agentEphemeralId), $msg);
        $agentEphemeralId = AmbientContext::testConfig()->sharedDataPerRequest->agentEphemeralId;
        TestCase::assertNotEmpty($agentEphemeralId);

        ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Setting agentEphemeralId...', ['agentEphemeralId' => $agentEphemeralId]);

        GlobalTracerHolder::getValue()->setAgentEphemeralId($agentEphemeralId);
    }
}
