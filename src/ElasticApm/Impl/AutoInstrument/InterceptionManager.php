<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\CallTrackerFactoryInterface;
use Elastic\Apm\AutoInstrument\CallTrackerInterface;
use Elastic\Apm\AutoInstrument\RegistrationContextInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class InterceptionManager
{
    /** @var CallTrackerFactoryInterface[] */
    private static $callTrackerFactories;

    public static function init(): void
    {
        self::loadPlugins();
    }

    /**
     * Called by elasticapm extension
     *
     * @noinspection PhpUnused
     *
     * @param int   $funcToInterceptId
     * @param mixed ...$interceptedCallArgs
     *
     * @return null|CallTrackerInterface
     */
    public static function interceptedCallPreHook(
        int $funcToInterceptId,
        ...$interceptedCallArgs
    ): ?CallTrackerInterface {
        return self::onInterceptedCallBegin($funcToInterceptId, ...$interceptedCallArgs);
    }

    /**
     * Called by elasticapm extension
     *
     * @noinspection PhpUnused
     *
     * @param CallTrackerInterface $callTracker
     * @param mixed                $returnValue Return value of the intercepted call
     */
    public static function interceptedCallPostHook(CallTrackerInterface $callTracker, $returnValue): void
    {
        $callTracker->onCallNormalEnd($returnValue);
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

    private static function loadPlugins(): void
    {
        $registerCtx = new class implements RegistrationContextInterface {

            /** @var CallTrackerFactoryInterface[] */
            public $callTrackerFactories = [];

            public function interceptCallsToInternalMethod(
                string $className,
                string $methodName,
                CallTrackerFactoryInterface $callTrackerFactory
            ): void {
                /**
                 * elasticapm_* functions are provided by the elasticapm extension
                 *
                 * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
                 * @phpstan-ignore-next-line
                 */
                $funcToInterceptId = \elasticapm_intercept_calls_to_internal_method($className, $methodName);
                if ($funcToInterceptId >= 0) {
                    $this->callTrackerFactories[$funcToInterceptId] = $callTrackerFactory;
                }
            }
        };

        BuiltinAutoInstrumentations::register($registerCtx);
        // self::loadConfiguredPlugins();

        self::$callTrackerFactories = $registerCtx->callTrackerFactories;
    }

    /**
     * @param int   $funcToInterceptId
     * @param mixed ...$interceptedCallArgs
     *
     * @return null|CallTrackerInterface
     */
    private static function onInterceptedCallBegin(
        int $funcToInterceptId,
        ...$interceptedCallArgs
    ): ?CallTrackerInterface {
        return self::$callTrackerFactories[$funcToInterceptId]->onCallBegin(...$interceptedCallArgs);
    }
}
