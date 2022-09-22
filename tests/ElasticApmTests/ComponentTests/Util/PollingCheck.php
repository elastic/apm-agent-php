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

use Closure;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\TimeUtil;
use ElasticApmTests\Util\LogCategoryForTests;

final class PollingCheck
{
    /** @var Logger */
    private $logger;

    /** @var string */
    private $dbgDesc;

    /** @var int */
    private $maxWaitTimeInMicroseconds;

    /** @var int */
    private $sleepTimeInMicroseconds = 1000 * 1000; // 1 second

    /** @var int */
    private $reportIntervalInMicroseconds = 1000 * 1000; // 1 second

    public function __construct(string $dbgDesc, int $maxWaitTimeInMicroseconds)
    {
        $this->dbgDesc = $dbgDesc;
        $this->maxWaitTimeInMicroseconds = $maxWaitTimeInMicroseconds;
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );
    }

    /**
     * @param Closure                   $check
     *
     * @phpstan-param   Closure(): bool $check
     *
     * @return bool
     */
    public function run(Closure $check): bool
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Starting to check if ' . $this->dbgDesc . '...',
            ['maxWaitTime' => TimeUtil::formatDurationInMicroseconds($this->maxWaitTimeInMicroseconds)]
        );

        $numberOfAttempts = 0;
        $sinceStarted = new Stopwatch();
        $sinceLastReport = new Stopwatch();
        while (true) {
            ++$numberOfAttempts;
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Starting attempt ' . $numberOfAttempts . ' to check if ' . $this->dbgDesc . '...');
            /** @noinspection PhpIfWithCommonPartsInspection */
            if ($check()) {
                $elapsedTime = $sinceStarted->elapsedInMicroseconds();
                ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Successfully completed checking if ' . $this->dbgDesc,
                    ['elapsedTime' => TimeUtil::formatDurationInMicroseconds($elapsedTime)]
                );
                return true;
            }

            $elapsedTime = $sinceStarted->elapsedInMicroseconds();
            if ($elapsedTime >= $this->maxWaitTimeInMicroseconds) {
                break;
            }

            if ($sinceLastReport->elapsedInMicroseconds() >= $this->reportIntervalInMicroseconds) {
                ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Still checking if ' . $this->dbgDesc . '...',
                    [
                        'elapsedTime'      => TimeUtil::formatDurationInMicroseconds($elapsedTime),
                        'numberOfAttempts' => $numberOfAttempts,
                        'maxWaitTime'      => TimeUtil::formatDurationInMicroseconds($this->maxWaitTimeInMicroseconds),
                    ]
                );
                $sinceLastReport->restart();
            }

            ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Sleeping ' . TimeUtil::formatDurationInMicroseconds($this->sleepTimeInMicroseconds)
                . ' before checking again if ' . $this->dbgDesc
                . ' (numberOfAttempts: ' . $numberOfAttempts . ')'
                . '...'
            );
            usleep($this->sleepTimeInMicroseconds);
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Reached max wait time while checking if ' . $this->dbgDesc,
            [
                'elapsedTime'      => TimeUtil::formatDurationInMicroseconds($sinceStarted->elapsedInMicroseconds()),
                'numberOfAttempts' => $numberOfAttempts,
                'maxWaitTime'      => TimeUtil::formatDurationInMicroseconds($this->maxWaitTimeInMicroseconds),
            ]
        );
        return false;
    }
}
