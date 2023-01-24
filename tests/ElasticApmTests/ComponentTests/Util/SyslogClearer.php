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
use ElasticApmTests\TestsRootDir;
use ElasticApmTests\Util\FileUtilForTests;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SyslogClearer extends TestInfraHttpServerProcessBase
{
    public const CLEAR_SYSLOG_URI_PATH = '/clear_syslog';

    protected function processConfig(): void
    {
        parent::processConfig();

        TestCase::assertTrue(
            isset(AmbientContextForTests::testConfig()->dataPerProcess->rootProcessId), // @phpstan-ignore-line
            LoggableToString::convert(AmbientContextForTests::testConfig())
        );
    }

    /** @inheritDoc */
    protected function shouldRegisterThisProcessWithResourcesCleaner(): bool
    {
        return false;
    }

    /** @inheritDoc */
    protected function processRequest(ServerRequestInterface $request): ?ResponseInterface
    {
        if ($request->getUri()->getPath() === self::CLEAR_SYSLOG_URI_PATH) {
            self::clearSyslog();
            return self::buildDefaultResponse();
        }

        return null;
    }

    private static function clearSyslog(): void
    {
        SyslogClearerClient::assertRunningAsRoot();

        $clearImplShellScriptFullPath = FileUtilForTests::listToPath(
            [TestsRootDir::$fullPath, 'tools', 'syslog_clearer', 'clear_impl.sh']
        );
        ProcessUtilForTests::startProcessAndWaitUntilExit(
            $clearImplShellScriptFullPath,
            EnvVarUtilForTests::getAll() /* <- envVars */,
            true /* <- shouldCaptureStdOutErr */,
            0 /* <- expectedExitCode */
        );
    }
}
