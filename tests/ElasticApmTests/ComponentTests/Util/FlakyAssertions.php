<?php

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Closure;
use Elastic\Apm\Impl\Config\BoolOptionParser;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\Util\LogCategoryForTests;
use PHPUnit\Framework\ExpectationFailedException;

final class FlakyAssertions
{
    use StaticClassTrait;

    private const ENABLED_ENV_VAR_NAME = 'ELASTIC_APM_PHP_TESTS_FLAKY_ASSERTIONS_ENABLED';
    private const ENABLED_DEFAULT_VALUE = false;

    /** @var bool */
    private static $areEnabled;

    /** @var Logger */
    private static $logger;

    private static function areEnabled(): bool
    {
        if (!isset(self::$areEnabled)) {
            $envVarValue = getenv(self::ENABLED_ENV_VAR_NAME);
            if ($envVarValue === false) {
                self::$areEnabled = self::ENABLED_DEFAULT_VALUE;
            } else {
                self::$areEnabled = (new BoolOptionParser())->parse($envVarValue);
            }
        }

        return self::$areEnabled;
    }

    private static function getLogger(): Logger
    {
        if (!isset(self::$logger)) {
            self::$logger = AmbientContext::loggerFactory()->loggerForClass(
                LogCategoryForTests::TEST_UTIL,
                __NAMESPACE__,
                __CLASS__,
                __FILE__
            );
        }

        return self::$logger;
    }

    /**
     * @param Closure $assertionCall
     *
     * @phpstan-param Closure(): void $assertionCall
     */
    public static function run(Closure $assertionCall): void
    {
        try {
            $assertionCall();
        } catch (ExpectationFailedException $ex) {
            if (self::areEnabled()) {
                throw $ex;
            }

            ($loggerProxy = self::getLogger()->ifNoticeLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->includeStacktrace()->logThrowable(
                $ex,
                'Flaky assertions are disabled but one has just failed'
            );
        }
    }
}
