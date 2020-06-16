<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class InterceptionManager
{
    /** @var callable[] */
    private static $onInterceptedCallBeginCallbacks;

    /** @var Logger */
    private static $logger;

    public static function init(Tracer $tracer): void
    {
        self::$logger = $tracer->loggerFactory()
                               ->loggerForClass(LogCategory::INTERCEPTION, __NAMESPACE__, __CLASS__, __FILE__);

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
     * @return OnInterceptedCallEndWrapper|null
     */
    public static function interceptedCallPreHook(
        int $funcToInterceptId,
        ...$interceptedCallArgs
    ): ?OnInterceptedCallEndWrapper {
        ($loggerProxy = self::$logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered', ['funcToInterceptId' => $funcToInterceptId]);

        $onInterceptedCallEnd = self::onInterceptedCallBegin($funcToInterceptId, ...$interceptedCallArgs);
        return is_null($onInterceptedCallEnd)
            ? null
            : new OnInterceptedCallEndWrapper($funcToInterceptId, $onInterceptedCallEnd);
    }

    /**
     * Called by elasticapm extension
     *
     * @noinspection PhpUnused
     *
     * @param OnInterceptedCallEndWrapper $onInterceptedCallEndWrapper
     * @param mixed|Throwable             $returnValueOrThrown Return value of the intercepted call or thrown object
     */
    public static function interceptedCallPostHook(
        OnInterceptedCallEndWrapper $onInterceptedCallEndWrapper,
        $returnValueOrThrown
    ): void {
        ($loggerProxy = self::$logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered', ['funcToInterceptId' => $onInterceptedCallEndWrapper->funcToInterceptId]);

        ($onInterceptedCallEndWrapper->wrappedOnInterceptedCallEndCallback)(
            false /* hasExitedByException */,
            $returnValueOrThrown
        );
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

        (new BuiltinPlugin())->register($registerCtx);
        // self::loadConfiguredPlugins();

        self::$onInterceptedCallBeginCallbacks = $registerCtx->onInterceptedCallBeginCallbacks;
    }

    /**
     * @param int   $funcToInterceptId
     * @param mixed ...$interceptedCallArgs
     *
     * @return callable|null
     */
    private static function onInterceptedCallBegin(
        int $funcToInterceptId,
        ...$interceptedCallArgs
    ): ?callable {
        /** @var callable */
        $onInterceptedCallBegin = self::$onInterceptedCallBeginCallbacks[$funcToInterceptId];
        return $onInterceptedCallBegin(...$interceptedCallArgs);
    }
}
