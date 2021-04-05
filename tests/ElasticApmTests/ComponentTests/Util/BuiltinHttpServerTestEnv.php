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

use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use ElasticApmTests\Util\LogCategoryForTests;

final class BuiltinHttpServerTestEnv extends HttpServerTestEnvBase
{
    private const APP_CODE_HOST_ROUTER_SCRIPT = 'routeToCliBuiltinHttpServerAppCodeHost.php';

    /** @var Logger */
    private $logger;

    public function __construct()
    {
        parent::__construct();

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    protected function ensureAppCodeHostServerRunning(TestProperties $testProperties): void
    {
        $this->ensureHttpServerIsRunning(
            $testProperties->urlParts->port /* <- ref */,
            $this->appCodeHostServerId /* <- ref */,
            ClassNameUtil::fqToShort(BuiltinHttpServerAppCodeHost::class) /* <- dbgServerDesc */,
            /* cmdLineGenFunc: */
            function (int $port) use ($testProperties) {
                return $testProperties->agentConfigSetter->appCodePhpCmd()
                       . " -S localhost:$port"
                       . ' "' . __DIR__ . DIRECTORY_SEPARATOR . self::APP_CODE_HOST_ROUTER_SCRIPT . '"';
            },
            false /* $keepElasticApmEnvVars */,
            $testProperties->agentConfigSetter->additionalEnvVars()
        );
    }
}
