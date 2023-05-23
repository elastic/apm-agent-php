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

use Clue\React\Docker\Client;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\LogCategoryForTests;
use ElasticApmTests\Util\TestCaseBase;

use function React\Async\await;

final class DockerUtil
{
    use StaticClassTrait;

    public static function getThisContainerId(): string
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);

        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_UTIL, __NAMESPACE__, __CLASS__, __FILE__);
        $loggerProxyDebug = $logger->ifDebugLevelEnabledNoLine(__FUNCTION__);

        TestCaseBase::assertTrue(AmbientContextForTests::testConfig()->isInContainer);
        $thisContainerImageName = AmbientContextForTests::testConfig()->thisContainerImageName;
        TestCaseBase::assertNotNull($thisContainerImageName);
        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, '', ['thisContainerImageName' => $thisContainerImageName]);
        $dbgCtx->add(['thisContainerImageName' => $thisContainerImageName]);

        $client = new Client();
        /** @var array<string, mixed>[] $runningContainers */
        $runningContainers = await($client->containerList());
        TestCaseBase::assertIsArray($runningContainers);
        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, '', ['runningContainers' => $runningContainers]);
        $dbgCtx->add(['runningContainers' => $runningContainers]);

        /** @var null|array<string, mixed> $foundContainer */
        $foundContainer = null;
        foreach ($runningContainers as $container) {
            if ($container['Image'] === $thisContainerImageName) {
                TestCaseBase::assertNull($foundContainer);
                $foundContainer = $container;
            }
        }
        TestCaseBase::assertNotNull($foundContainer);

        $foundContainerId = $foundContainer['Id'];
        TestCaseBase::assertIsString($foundContainerId);
        return $foundContainerId;
    }
}
