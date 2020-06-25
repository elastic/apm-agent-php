<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\Tests\Util\TestLogCategory;
use RuntimeException;
use Throwable;

abstract class AppCodeHostBase extends CliProcessBase
{
    /** @var Logger */
    private $logger;

    /** @var string */
    protected $appCodeClass;

    /** @var string */
    protected $appCodeMethod;

    public function __construct(string $runScriptFile)
    {
        if (!extension_loaded('elastic_apm')) {
            throw new RuntimeException(
                'Environment hosting component tests application code should have elastic_apm extension loaded.'
            );
        }

        parent::__construct($runScriptFile);

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            TestLogCategory::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    protected function registerWithSpawnedProcessesCleaner(): void
    {
        // We don't want any of the infrastructure operations to be recorded as application's APM events
        ElasticApm::pauseRecording();

        try {
            parent::registerWithSpawnedProcessesCleaner();
        } finally {
            ElasticApm::resumeRecording();
        }
    }

    protected function callAppCode(): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Calling application code...');

        try {
            /** @phpstan-ignore-next-line */
            call_user_func([$this->appCodeClass, $this->appCodeMethod]);
        } catch (Throwable $throwable) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Call to application code exited by exception', ['throwable' => $throwable]);
            throw new WrappedAppCodeException($throwable);
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Call to application code completed');
    }

    protected function toStringAddProperties(ObjectToStringBuilder $builder): void
    {
        parent::toStringAddProperties($builder);
        $builder->add('appCodeClass', $this->appCodeClass);
        $builder->add('appCodeMethod', $this->appCodeMethod);
    }
}
