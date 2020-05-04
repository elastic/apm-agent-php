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
     * @param int   $callToInterceptId
     * @param mixed ...$interceptedCallArgs
     *
     * @return null|CallTrackerInterface
     */
    public static function interceptedCallPreHook(
        int $callToInterceptId,
        ...$interceptedCallArgs
    ): ?CallTrackerInterface {
        return self::onInterceptedCallBegin($callToInterceptId, ...$interceptedCallArgs);
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
    //  * @param int   $callToInterceptId
    //  * @param mixed ...$interceptedCallArgs
    //  *
    //  * @return mixed
    //  * @throws Throwable
    //  */
    // public static function wrapInterceptedCall(int $callToInterceptId, ...$interceptedCallArgs)
    // {
    //     $callTracker = self::onInterceptedCallBegin($callToInterceptId, ...$interceptedCallArgs);
    //     if (is_null($callTracker)) {
    //         /**
    //          * elasticapm_* functions are provided by the elasticapm extension
    //          *
    //          * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
    //          * @phpstan-ignore-next-line
    //          */
    //         return \elasticapm_call_intercepted_original($callToInterceptId, ...$interceptedCallArgs);
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
    //             \elasticapm_call_intercepted_original($callToInterceptId, func_num_args(), func_get_args())
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

            public function interceptMethod(
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
                $callToInterceptId = \elasticapm_intercept_calls_to_method($className, $methodName);
                if ($callToInterceptId >= 0) {
                    $this->callTrackerFactories[$callToInterceptId] = $callTrackerFactory;
                }
            }
        };

        BuiltinAutoInstrumentations::register($registerCtx);
        // self::loadConfiguredPlugins();

        self::$callTrackerFactories = $registerCtx->callTrackerFactories;
    }

    /**
     * @param int   $callToInterceptId
     * @param mixed ...$interceptedCallArgs
     *
     * @return null|CallTrackerInterface
     */
    private static function onInterceptedCallBegin(
        int $callToInterceptId,
        ...$interceptedCallArgs
    ): ?CallTrackerInterface {
        return self::$callTrackerFactories[$callToInterceptId]->onCallBegin(...$interceptedCallArgs);
    }
}
