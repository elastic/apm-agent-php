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

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\Util\LogCategoryForTests;
use PHPUnit\Framework\TestCase;

final class SyslogClearerClient
{
    use StaticClassTrait;

    public static function assertRunningAsRoot(): void
    {
        TestCase::assertSame(
            0,
            posix_geteuid(),
            LoggableToString::convert(
                [
                    'message'                   => 'Effective user ID should 0 (i.e., root)',
                    'info about effective user' => posix_getpwuid(posix_geteuid()),
                ]
            )
        );
    }

    public static function startInBackground(): void
    {
        $localLogger = self::clientInit(__FUNCTION__);

        self::assertRunningAsRoot();

        $httpServerHandle = TestInfraHttpServerStarter::startTestInfraHttpServer(
            ClassNameUtil::fqToShort(SyslogClearer::class) /* <- dbgServerDesc */,
            'runSyslogClearer.php' /* <- runScriptName */,
            [HttpServerStarter::PORTS_RANGE_END - 2] /* <- portsInUse */,
            1 /* <- portsToAllocateCount */,
            null /* <- resourcesCleaner */
        );

        ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Exiting...', ['httpServerHandle' => $httpServerHandle]);

        echo $httpServerHandle->serialize();
    }

    public static function signalToClear(): void
    {
        $localLogger = self::clientInit(__FUNCTION__);

        $httpServerHandle = self::deserializeHttpServerHandleFromArgs($localLogger);

        $response = $httpServerHandle->sendRequest(
            HttpConstantsForTests::METHOD_POST,
            SyslogClearer::CLEAR_SYSLOG_URI_PATH
        );
        TestCase::assertSame(HttpConstantsForTests::STATUS_OK, $response->getStatusCode());

        ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Exiting...');
    }

    public static function stop(): void
    {
        $localLogger = self::clientInit(__FUNCTION__);

        $httpServerHandle = self::deserializeHttpServerHandleFromArgs($localLogger);

        $httpServerHandle->signalAndWaitForItToExit();

        ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Exiting...');
    }

    private static function clientInit(string $dbgEntryMethod): Logger
    {
        global $argv;
        TestCase::assertGreaterThanOrEqual(1, count($argv), LoggableToString::convert(['argv' => $argv]));
        AmbientContextForTests::init(ClassNameUtil::fqToShort(self::class) . '::' . $dbgEntryMethod);

        $localLogger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );

        ($loggerProxy = $localLogger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered', ['argv' => $argv, 'Environment variables' => EnvVarUtilForTests::getAll()]);

        return $localLogger;
    }

    private static function deserializeHttpServerHandleFromArgs(Logger $logger): HttpServerHandle
    {
        global $argv;
        TestCase::assertGreaterThanOrEqual(2, count($argv), LoggableToString::convert(['argv' => $argv]));

        $httpServerHandle = HttpServerHandle::deserialize($argv[1]);

        ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Parsed command', ['httpServerHandle' => $httpServerHandle, 'argv' => $argv]);

        return $httpServerHandle;
    }
}
