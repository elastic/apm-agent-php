<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\Assert;
use Elastic\Apm\Impl\Util\HiddenConstructorTrait;
use RuntimeException;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class PhpPartFacade
{
    /**
     * Constructor is hidden because instance() should be used instead
     */
    use HiddenConstructorTrait;

    /** @var self|null */
    private static $singletonInstance = null;

    /** @var TransactionForExtensionRequest|null */
    private $transactionForExtensionRequest = null;

    /** @var InterceptionManager|null */
    private $interceptionManager = null;

    private function __construct(float $requestInitStartTime)
    {
        if (!extension_loaded('elasticapm')) {
            throw new RuntimeException('elasticapm extension is not loaded');
        }

        $tracer = self::buildTracer();
        if (is_null($tracer)) {
            BootstrapStageLogger::logDebug(
                'Cutting bootstrap sequence short - tracing is disabled',
                __LINE__,
                __FUNCTION__
            );
            return;
        }

        $this->transactionForExtensionRequest = new TransactionForExtensionRequest($tracer, $requestInitStartTime);
        $this->interceptionManager = new InterceptionManager($tracer);
    }

    /**
     * Called by elasticapm extension
     *
     * @noinspection PhpUnused
     *
     * @param int   $maxEnabledLogLevel
     * @param float $requestInitStartTime
     *
     * @return bool
     */
    public static function bootstrap(int $maxEnabledLogLevel, float $requestInitStartTime): bool
    {
        BootstrapStageLogger::configure($maxEnabledLogLevel);
        BootstrapStageLogger::logDebug(
            'Starting bootstrap sequence...' . " maxEnabledLogLevel: $maxEnabledLogLevel",
            __LINE__,
            __FUNCTION__
        );

        if (!is_null(self::$singletonInstance)) {
            BootstrapStageLogger::logCritical(
                'bootstrap() is called even though singletonInstance is already created'
                . ' (probably bootstrap() is called more than once)',
                __LINE__,
                __FUNCTION__
            );
            return false;
        }

        try {
            self::$singletonInstance = new self($requestInitStartTime);
        } catch (Throwable $throwable) {
            BootstrapStageLogger::logCriticalThrowable(
                $throwable,
                'One of the steps in bootstrap sequence let a throwable escape',
                __LINE__,
                __FUNCTION__
            );
            return false;
        }

        BootstrapStageLogger::logDebug('Successfully completed bootstrap sequence', __LINE__, __FUNCTION__);
        return true;
    }

    private static function singletonInstance(): self
    {
        if (is_null(self::$singletonInstance)) {
            throw new RuntimeException(
                'Trying to use singleton instance that is not set'
                . ' (probably either before call to bootstrap() or after failed call to bootstrap())'
            );
        }

        return self::$singletonInstance;
    }

    /**
     * Called by elasticapm extension
     *
     * @noinspection PhpUnused
     *
     * @param int   $funcToInterceptId
     * @param mixed ...$interceptedCallArgs
     *
     * @return callable|null
     * @throws Throwable
     */
    public static function interceptedCallPreHook(
        int $funcToInterceptId,
        ...$interceptedCallArgs
    ): ?callable {
        if (is_null(self::singletonInstance()->interceptionManager)) {
            return null;
        }
        try {
            return self::singletonInstance()->interceptionManager->interceptedCallPreHook(
                $funcToInterceptId,
                ...$interceptedCallArgs
            );
        } catch (Throwable $throwable) {
            BootstrapStageLogger::logCriticalThrowable(
                $throwable,
                'Intercepted call pre-hook let a throwable escape',
                __LINE__,
                __FUNCTION__
            );
            throw $throwable;
        }
    }

    /**
     * Called by elasticapm extension
     *
     * @noinspection PhpUnused
     *
     * @param int             $funcToInterceptId
     * @param callable        $onInterceptedCallEnd
     * @param mixed|Throwable $returnValueOrThrown Return value of the intercepted call
     *                                             or the object thrown by the intercepted call
     *
     * @throws Throwable
     */
    public static function interceptedCallPostHook(
        int $funcToInterceptId,
        callable $onInterceptedCallEnd,
        $returnValueOrThrown
    ): void {
        if (is_null(self::singletonInstance()->interceptionManager)) {
            return;
        }

        try {
            self::singletonInstance()->interceptionManager->interceptedCallPostHook(
                $funcToInterceptId,
                $onInterceptedCallEnd,
                $returnValueOrThrown
            );
        } catch (Throwable $throwable) {
            BootstrapStageLogger::logCriticalThrowable(
                $throwable,
                'Intercepted call post-hook let a throwable escape',
                __LINE__,
                __FUNCTION__
            );
            throw $throwable;
        }
    }

    /**
     * Called by elasticapm extension
     *
     * @noinspection PhpUnused
     */
    public static function shutdown(): void
    {
        BootstrapStageLogger::logDebug('Starting shutdown sequence...', __LINE__, __FUNCTION__);

        try {
            self::singletonInstance()->shutdownImpl();
        } catch (Throwable $throwable) {
            BootstrapStageLogger::logCriticalThrowable(
                $throwable,
                'One of the steps in shutdown sequence let a throwable escape - skipping the rest of the steps',
                __LINE__,
                __FUNCTION__
            );
        }

        self::$singletonInstance = null;
        BootstrapStageLogger::logDebug('Successfully completed shutdown sequence', __LINE__, __FUNCTION__);
    }

    private function shutdownImpl(): void
    {
        BootstrapStageLogger::logDebug('Starting shutdown sequence...', __LINE__, __FUNCTION__);

        try {
            if (!is_null($this->transactionForExtensionRequest)) {
                $this->transactionForExtensionRequest->onShutdown();
            }
        } catch (Throwable $throwable) {
            BootstrapStageLogger::logCriticalThrowable(
                $throwable,
                'One of the steps in shutdown sequence let a throwable escape - skipping the rest of the steps',
                __LINE__,
                __FUNCTION__
            );
        }
    }

    /**
     * @return Tracer|null
     */
    private static function buildTracer(): ?Tracer
    {
        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that(!GlobalTracerHolder::isSet())
        && $assertProxy->info(
            '!GlobalTracerHolder::isSet()',
            ['GlobalTracerHolder::get()' => GlobalTracerHolder::get()]
        );

        $tracer = GlobalTracerHolder::get();
        if ($tracer->isNoop()) {
            return null;
        }

        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that($tracer instanceof Tracer)
        && $assertProxy->info('$tracer instanceof Tracer', ['get_class($tracer)' => get_class($tracer)]);
        assert($tracer instanceof Tracer);

        return $tracer;
    }
}
