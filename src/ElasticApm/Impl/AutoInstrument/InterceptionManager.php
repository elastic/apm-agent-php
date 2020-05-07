<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\OnInterceptedCallBeginInterface;
use Elastic\Apm\AutoInstrument\OnInterceptedCallEndInterface;
use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class InterceptionManager
{
    /** @var OnInterceptedCallBeginInterface[] */
    private static $registeredCallbacks;

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
     * @return OnInterceptedCallEndInterface|null
     */
    public static function interceptedCallPreHook(
        int $funcToInterceptId,
        ...$interceptedCallArgs
    ): ?OnInterceptedCallEndInterface {
        self::logTrace("Entered. funcToInterceptId: $funcToInterceptId", __LINE__, __FUNCTION__);
        return self::onInterceptedCallBegin($funcToInterceptId, ...$interceptedCallArgs);
    }

    /**
     * Called by elasticapm extension
     *
     * @noinspection PhpUnused
     *
     * @param OnInterceptedCallEndInterface $onInterceptedCallEnd
     * @param mixed|Throwable               $returnValueOrThrown Return value of the intercepted call or thrown object
     */
    public static function interceptedCallPostHook(
        OnInterceptedCallEndInterface $onInterceptedCallEnd,
        $returnValueOrThrown
    ): void {
        self::logTrace("Entered", __LINE__, __FUNCTION__);
        // TODO: Sergey Kleyman: Implement: getting real value for hasExitedByException
        $onInterceptedCallEnd->onInterceptedCallEnd(/* hasExitedByException */ false, $returnValueOrThrown);
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

            /** @var OnInterceptedCallBeginInterface[] */
            public $registeredCallbacks = [];

            public function interceptCallsToInternalMethod(
                string $className,
                string $methodName,
                OnInterceptedCallBeginInterface $onInterceptedCallBegin
            ): void {
                /**
                 * elasticapm_* functions are provided by the elasticapm extension
                 *
                 * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
                 * @phpstan-ignore-next-line
                 */
                $funcToInterceptId = \elasticapm_intercept_calls_to_internal_method($className, $methodName);
                if ($funcToInterceptId >= 0) {
                    $this->registeredCallbacks[$funcToInterceptId] = $onInterceptedCallBegin;
                }
            }

            public function interceptCallsToInternalFunction(
                string $functionName,
                OnInterceptedCallBeginInterface $onInterceptedCallBegin
            ): void {
                /**
                 * elasticapm_* functions are provided by the elasticapm extension
                 *
                 * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
                 * @phpstan-ignore-next-line
                 */
                $funcToInterceptId = \elasticapm_intercept_calls_to_internal_function($functionName);
                if ($funcToInterceptId >= 0) {
                    $this->registeredCallbacks[$funcToInterceptId] = $onInterceptedCallBegin;
                }
            }
        };

        (new BuiltinPlugin())->register($registerCtx);
        // self::loadConfiguredPlugins();

        self::$registeredCallbacks = $registerCtx->registeredCallbacks;
    }

    /**
     * @param int   $funcToInterceptId
     * @param mixed ...$interceptedCallArgs
     *
     * @return OnInterceptedCallEndInterface|null
     */
    private static function onInterceptedCallBegin(
        int $funcToInterceptId,
        ...$interceptedCallArgs
    ): ?OnInterceptedCallEndInterface {
        return self::$registeredCallbacks[$funcToInterceptId]->onInterceptedCallBegin(...$interceptedCallArgs);
    }

    // TODO: Sergey Kleyman: Switch to Logger
    private static function logTrace(
        string $message,
        int $sourceCodeLine,
        string $sourceCodeFunc
    ): void {
        /** @noinspection PhpUndefinedConstantInspection */
        self::log(
        /**
         * ELASTICAPM_* constants are provided by the elasticapm extension
         *
         * @phpstan-ignore-next-line
         */
            ELASTICAPM_LOG_LEVEL_TRACE,
            $message,
            $sourceCodeLine,
            $sourceCodeFunc
        );
    }

    private static function log(
        int $statementLevel,
        string $message,
        int $sourceCodeLine,
        string $sourceCodeFunc
    ): void {
        /**
         * elasticapm_* functions are provided by the elasticapm extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         * @phpstan-ignore-next-line
         */
        \elasticapm_log(
            0 /* $isForced */,
            $statementLevel,
            __FILE__,
            $sourceCodeLine,
            $sourceCodeFunc,
            $message
        );
    }
}
