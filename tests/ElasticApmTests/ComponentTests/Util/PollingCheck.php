<?php

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Closure;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
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
    private $sleepTimeInMicroseconds = 100 * 1000; // 100 milliseconds

    /** @var int */
    private $reportIntervalInMicroseconds = 1 * 1000 * 1000; // 1 second

    public function __construct(string $dbgDesc, int $maxWaitTimeInMicroseconds, LoggerFactory $loggerFactory)
    {
        $this->dbgDesc = $dbgDesc;
        $this->maxWaitTimeInMicroseconds = $maxWaitTimeInMicroseconds;
        $this->logger = $loggerFactory->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );
    }

    /**
     * @param Closure $check
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
            ['maxWaitTime' => TimeFormatUtil::formatDurationInMicroseconds($this->maxWaitTimeInMicroseconds)]
        );

        $numberOfAttempts = 0;
        $sinceStarted = new Stopwatch();
        $sinceLastReport = new Stopwatch();
        while (true) {
            ++$numberOfAttempts;
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Starting attempt ' . $numberOfAttempts . ' to check if ' . $this->dbgDesc . '...');
            if ($check()) {
                ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Successfully completed checking if ' . $this->dbgDesc,
                    [
                        'elapsedTime' =>
                            TimeFormatUtil::formatDurationInMicroseconds($sinceStarted->elapsedInMicroseconds()),
                    ]
                );
                return true;
            }

            if ($sinceStarted->elapsedInMicroseconds() >= $this->maxWaitTimeInMicroseconds) {
                break;
            }

            if ($sinceLastReport->elapsedInMicroseconds() >= $this->reportIntervalInMicroseconds) {
                ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Still checking if ' . $this->dbgDesc . '...',
                    [
                        'elapsedTime'      =>
                            TimeFormatUtil::formatDurationInMicroseconds($sinceStarted->elapsedInMicroseconds()),
                        'numberOfAttempts' => $numberOfAttempts,
                        'maxWaitTime'      =>
                            TimeFormatUtil::formatDurationInMicroseconds($this->maxWaitTimeInMicroseconds),
                    ]
                );
                $sinceLastReport->restart();
            }

            ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Sleeping ' . TimeFormatUtil::formatDurationInMicroseconds($this->sleepTimeInMicroseconds)
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
                'elapsedTime'      =>
                    TimeFormatUtil::formatDurationInMicroseconds($sinceStarted->elapsedInMicroseconds()),
                'numberOfAttempts' => $numberOfAttempts,
                'maxWaitTime'      =>
                    TimeFormatUtil::formatDurationInMicroseconds($this->maxWaitTimeInMicroseconds),
            ]
        );
        return false;
    }
}
