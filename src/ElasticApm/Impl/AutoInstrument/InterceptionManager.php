<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\InterceptedCallTrackerInterface;
use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\DbgUtil;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class InterceptionManager
{
    /** @var callable[] */
    private $interceptedCallTrackerFactories;

    /** @var Logger */
    private $logger;

    public function __construct(Tracer $tracer)
    {
        $this->logger = $tracer->loggerFactory()
                               ->loggerForClass(LogCategory::INTERCEPTION, __NAMESPACE__, __CLASS__, __FILE__);

        $this->loadPlugins();
    }

    /**
     * @param int   $funcToInterceptId
     * @param mixed ...$interceptedCallArgs
     *
     * @return mixed
     * @throws Throwable
     */
    public function interceptedCall(int $funcToInterceptId, ...$interceptedCallArgs)
    {
        $localLogger = $this->logger->inherit()->addContext('funcToInterceptId', $funcToInterceptId);

        $callTracker = $this->interceptedCallPreHook($localLogger, $funcToInterceptId, ...$interceptedCallArgs);

        $hasExitedByException = false;
        try {
            /**
             * elasticapm_* functions are provided by the elasticapm extension
             *
             * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
             * @phpstan-ignore-next-line
             */
            $returnValueOrThrown = \elasticapm_call_intercepted_original();
        } catch (Throwable $throwable) {
            $hasExitedByException = true;
            $returnValueOrThrown = $throwable;
        }

        if (!is_null($callTracker)) {
            $this->interceptedCallPostHook($localLogger, $callTracker, $hasExitedByException, $returnValueOrThrown);
        }

        if ($hasExitedByException) {
            throw $returnValueOrThrown;
        }

        return $returnValueOrThrown;
    }

    /**
     * @param Logger $localLogger
     * @param int    $funcToInterceptId
     * @param mixed  ...$interceptedCallArgs
     *
     * @return mixed
     */
    private function interceptedCallPreHook(
        Logger $localLogger,
        int $funcToInterceptId,
        ...$interceptedCallArgs
    ): ?InterceptedCallTrackerInterface {
        ($loggerProxy = $localLogger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered');

        try {
            /** @var InterceptedCallTrackerInterface $callTracker */
            $callTracker = $this->interceptedCallTrackerFactories[$funcToInterceptId]();
        } catch (Throwable $throwable) {
            ($loggerProxy = $localLogger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                DbgUtil::fqToShortClassName(InterceptedCallTrackerInterface::class)
                . ' factory has let a Throwable to escape - returning null',
                ['throwable' => $throwable]
            );

            ($loggerProxy = $localLogger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Exiting (with null return value)');
            return null;
        }

        try {
            $callTracker->preHook(...$interceptedCallArgs);
        } catch (Throwable $throwable) {
            ($loggerProxy = $localLogger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                DbgUtil::fqToShortClassName(InterceptedCallTrackerInterface::class)
                . 'preHook() has let a Throwable to escape',
                ['throwable' => $throwable]
            );

            ($loggerProxy = $localLogger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Exiting - returning null');
            return null;
        }

        ($loggerProxy = $localLogger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Exiting - returning non-null');
        return $callTracker;
    }

    /**
     * @param Logger                          $localLogger
     * @param InterceptedCallTrackerInterface $callTracker
     * @param bool                            $hasExitedByException
     * @param mixed|Throwable                 $returnValueOrThrown      Return value of the intercepted call
     *                                                                  or the object thrown by the intercepted call
     */
    private function interceptedCallPostHook(
        Logger $localLogger,
        InterceptedCallTrackerInterface $callTracker,
        bool $hasExitedByException,
        $returnValueOrThrown
    ): void {
        ($loggerProxy = $localLogger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered');

        try {
            $callTracker->postHook($hasExitedByException, $returnValueOrThrown);
        } catch (Throwable $throwable) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                DbgUtil::fqToShortClassName(InterceptedCallTrackerInterface::class)
                . 'postHook() has let a Throwable to escape',
                ['throwable' => $throwable]
            );
        }
    }

    // /**
    //  * Called by elasticapm extension
    //  *
    //  * @noinspection PhpUnused
    //  *
    //  * @param int   $funcToInterceptId
    //  * @param mixed ...$interceptedCallArgs
    //  *
    //  * @return mixed
    //  * @throws Throwable
    //  */
    // public static function wrapInterceptedCall(int $funcToInterceptId, ...$interceptedCallArgs)
    // {
    //     $callTracker = self::onInterceptedCallBegin($funcToInterceptId, ...$interceptedCallArgs);
    //     if (is_null($callTracker)) {
    //         /**
    //          * elasticapm_* functions are provided by the elasticapm extension
    //          *
    //          * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
    //          * @phpstan-ignore-next-line
    //          */
    //         return \elasticapm_call_intercepted_original($funcToInterceptId, ...$interceptedCallArgs);
    //     }
    //
    //     try {
    //         /**
    //          * elasticapm_* functions are provided by the elasticapm extension
    //          *
    //          * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
    //          * @phpstan-ignore-next-line
    //          */
    //         return $callTracker->onCallNormalEnd(
    //             \elasticapm_call_intercepted_original($funcToInterceptId, func_num_args(), func_get_args())
    //         );
    //     } catch (Throwable $throwable) {
    //         throw $callTracker->onCallEndByException($throwable);
    //     }
    // }

    private function loadPlugins(): void
    {
        $registerCtx = new class implements RegistrationContextInterface {

            /** @var callable[] */
            public $onInterceptedCallBeginCallbacks;

            public function interceptCallsToMethod(
                string $className,
                string $methodName,
                callable $onInterceptedCallBegin
            ): void {
                /**
                 * elasticapm_* functions are provided by the elasticapm extension
                 *
                 * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
                 * @phpstan-ignore-next-line
                 */
                $funcToInterceptId = \elasticapm_intercept_calls_to_internal_method($className, $methodName);
                if ($funcToInterceptId >= 0) {
                    $this->onInterceptedCallBeginCallbacks[$funcToInterceptId] = $onInterceptedCallBegin;
                }
            }

            public function interceptCallsToFunction(
                string $functionName,
                callable $onInterceptedCallBegin
            ): void {
                /**
                 * elasticapm_* functions are provided by the elasticapm extension
                 *
                 * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
                 * @phpstan-ignore-next-line
                 */
                $funcToInterceptId = \elasticapm_intercept_calls_to_internal_function($functionName);
                if ($funcToInterceptId >= 0) {
                    $this->onInterceptedCallBeginCallbacks[$funcToInterceptId] = $onInterceptedCallBegin;
                }
            }
        };

        $this->loadPluginsImpl($registerCtx);

        $this->interceptedCallTrackerFactories = $registerCtx->onInterceptedCallBeginCallbacks;
    }

    private function loadPluginsImpl(RegistrationContextInterface $registerCtx): void
    {
        (new BuiltinPlugin())->register($registerCtx);
        // self::loadConfiguredPlugins();
    }
}
