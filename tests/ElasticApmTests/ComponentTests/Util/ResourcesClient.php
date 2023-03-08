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
use Elastic\Apm\Impl\Util\BoolUtil;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\UrlParts;
use ElasticApmTests\Util\LogCategoryForTests;
use RuntimeException;

final class ResourcesClient
{
    /** @var Logger */
    private $logger;

    /** @var string */
    private $resourcesCleanerSpawnedProcessInternalId;

    /** @var int */
    private $resourcesCleanerPort;

    public function __construct(string $resourcesCleanerSpawnedProcessInternalId, int $resourcesCleanerPort)
    {
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        $this->resourcesCleanerSpawnedProcessInternalId = $resourcesCleanerSpawnedProcessInternalId;
        $this->resourcesCleanerPort = $resourcesCleanerPort;
    }

    /** @noinspection PhpSameParameterValueInspection */
    private function registerFileToDelete(string $fullPath, bool $isTestScoped): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Registering file to delete with ' . ClassNameUtil::fqToShort(ResourcesCleaner::class) . '...',
            ['fullPath' => $fullPath]
        );

        $response = HttpClientUtilForTests::sendRequest(
            HttpConstantsForTests::METHOD_POST,
            (new UrlParts())
                ->path(ResourcesCleaner::REGISTER_FILE_TO_DELETE_URI_PATH)
                ->port($this->resourcesCleanerPort),
            TestInfraDataPerRequest::withSpawnedProcessInternalId($this->resourcesCleanerSpawnedProcessInternalId),
            /* headers: */
            [
                ResourcesCleaner::PATH_QUERY_HEADER_NAME           => $fullPath,
                ResourcesCleaner::IS_TEST_SCOPED_QUERY_HEADER_NAME => BoolUtil::toString($isTestScoped),
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
            'Successfully registered file to delete with ' . ClassNameUtil::fqToShort(ResourcesCleaner::class),
            ['fullPath' => $fullPath]
        );
    }

    public function createTempFile(string $type, bool $shouldBeDeletedOnTestExit = true): string
    {
        $fileNamePrefix = 'Elastic_APM_PHP_Agent_component_tests_-_' . $type . '_-_';
        $tempFileFullPath = tempnam(sys_get_temp_dir(), $fileNamePrefix);
        if ($tempFileFullPath === false) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    'Failed to create a temporary INI file',
                    [
                        'type'               => $type,
                        'sys_get_temp_dir()' => sys_get_temp_dir(),
                        'fileNamePrefix'     => $fileNamePrefix,
                    ]
                )
            );
        }

        if ($shouldBeDeletedOnTestExit) {
            $this->registerFileToDelete($tempFileFullPath, /* isTestScoped */ true);
        }
        return $tempFileFullPath;
    }
}
