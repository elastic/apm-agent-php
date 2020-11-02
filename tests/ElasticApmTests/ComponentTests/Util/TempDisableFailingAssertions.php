<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Tests\Util\LogCategoryForTests;

final class TempDisableFailingAssertions
{
    use StaticClassTrait;

    /** @var bool */
    public static $shouldDisableFailingAssertions = false;

    public static function checkDisableFailedAssertion(
        string $assertionSrcFile,
        int $assertionSrcLine,
        bool $assertionResult,
        string $assertionText,
        string $context
    ): void {
        if ($assertionResult) {
            return;
        }

        $logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );

        ($loggerProxy = $logger->ifNoticeLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Temporarily disabled assertion (that is failing for component tests) has indeed failed',
            [
                'location'  => $assertionSrcFile . ':' . $assertionSrcLine,
                'assertion' => $assertionText,
                'context'   => $context,
            ]
        );
    }
}
