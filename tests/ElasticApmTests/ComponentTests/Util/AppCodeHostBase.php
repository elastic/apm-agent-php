<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Tests\Util\LogCategoryForTests;
use RuntimeException;
use Throwable;

abstract class AppCodeHostBase extends CliProcessBase
{
    /** @var array<string, mixed>|null */
    public $appCodeArgs;

    /** @var string */
    protected $appCodeClass;

    /** @var string */
    protected $appCodeMethod;

    /** @var Logger */
    private $logger;

    public function __construct()
    {
        if (!extension_loaded('elastic_apm')) {
            throw new RuntimeException(
                'Environment hosting component tests application code should have elastic_apm extension loaded.'
                . ' php_ini_loaded_file(): ' . php_ini_loaded_file() . '.'
            );
        }

        parent::__construct();

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Done', ['Environment variables' => getenv()]);
    }

    abstract protected function runImpl(): void;

    public static function run(?string &$topLevelCodeId): void
    {
        self::runSkeleton(
            function (CliProcessBase $thisObjArg) use (&$topLevelCodeId): void {
                $topLevelCodeId = AmbientContext::config()->sharedDataPerRequest->appTopLevelCodeId;
                if (!is_null($topLevelCodeId)) {
                    return;
                }

                /** var AppCodeHostBase */
                $thisObj = $thisObjArg;
                $thisObj->runImpl(); // @phpstan-ignore-line
            }
        );
    }

    protected function registerWithResourcesCleaner(): void
    {
        // We don't want any of the infrastructure operations to be recorded as application's APM events
        ElasticApm::pauseRecording();

        try {
            parent::registerWithResourcesCleaner();
        } finally {
            ElasticApm::resumeRecording();
        }
    }

    protected function callAppCode(): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Calling application code...');

        TestAssertUtil::assertThat(
            !is_null(AmbientContext::config()->sharedDataPerRequest->appCodeClass)
            && !is_null(AmbientContext::config()->sharedDataPerRequest->appCodeMethod),
            strval(AmbientContext::config())
        );

        try {
            $methodToCall = [
                AmbientContext::config()->sharedDataPerRequest->appCodeClass,
                AmbientContext::config()->sharedDataPerRequest->appCodeMethod,
            ];
            if (is_null(AmbientContext::config()->sharedDataPerRequest->appCodeArguments)) {
                /** @phpstan-ignore-next-line */
                call_user_func($methodToCall);
            } else {
                /** @phpstan-ignore-next-line */
                call_user_func($methodToCall, AmbientContext::config()->sharedDataPerRequest->appCodeArguments);
            }
        } catch (Throwable $throwable) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Call to application code exited by exception', ['throwable' => $throwable]);
            throw new WrappedAppCodeException($throwable);
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Call to application code completed');
    }
}
