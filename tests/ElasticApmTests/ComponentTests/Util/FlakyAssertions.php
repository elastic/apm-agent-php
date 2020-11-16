<?php

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Closure;
use Elastic\Apm\Impl\Config\BoolOptionParser;
use Elastic\Apm\Impl\Log\LoggableToEncodedJson;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use PHPUnit\Framework\ExpectationFailedException;

final class FlakyAssertions
{
    use StaticClassTrait;

    private const ENABLED_ENV_VAR_NAME = 'ELASTIC_APM_PHP_TESTS_FLAKY_ASSERTIONS_ENABLED';
    private const ENABLED_DEFAULT_VALUE = false;

    /** @var bool */
    private static $areEnabled;

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

    /**
     * @param Closure $assertionCall
     * @param bool    $forceEnableFlakyAssertions
     *
     * @phpstan-param Closure(): void $assertionCall
     */
    public static function run(Closure $assertionCall, bool $forceEnableFlakyAssertions = false): void
    {
        try {
            $assertionCall();
        } catch (ExpectationFailedException $ex) {
            if ($forceEnableFlakyAssertions || self::areEnabled()) {
                throw $ex;
            }

            fwrite(
                STDERR,
                PHP_EOL . __METHOD__ . ': ' . 'Flaky assertions are disabled but one has just failed' . PHP_EOL
                . '+-> Exception:' . PHP_EOL
                . LoggableToEncodedJson::convert($ex) . PHP_EOL
                . '+-> Stack trace:' . PHP_EOL
                . $ex->getTraceAsString() . PHP_EOL
            );
        }
    }
}
