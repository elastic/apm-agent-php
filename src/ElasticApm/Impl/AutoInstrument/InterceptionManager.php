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
    private $onInterceptedCallBeginCallbacks;

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
     * @return callable|null
     */
    public function interceptedCallPreHook(
        int $funcToInterceptId,
        ...$interceptedCallArgs
    ): ?callable {
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered', ['funcToInterceptId' => $funcToInterceptId]);

        return $this->onInterceptedCallBeginCallbacks[$funcToInterceptId](...$interceptedCallArgs);
    }

    /**
     * @param int             $funcToInterceptId
     * @param callable        $onInterceptedCallEnd
     * @param mixed|Throwable $returnValueOrThrown Return value of the intercepted call
     *                                             or the object thrown by the intercepted call
     */
    public function interceptedCallPostHook(
        int $funcToInterceptId,
        callable $onInterceptedCallEnd,
        $returnValueOrThrown
    ): void {
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered', ['funcToInterceptId' => $funcToInterceptId]);

        $onInterceptedCallEnd(false /* hasExitedByException */, $returnValueOrThrown);
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

        $this->onInterceptedCallBeginCallbacks = $registerCtx->onInterceptedCallBeginCallbacks;
    }

    private function loadPluginsImpl(RegistrationContextInterface $registerCtx): void
    {
        (new BuiltinPlugin())->register($registerCtx);
        // self::loadConfiguredPlugins();
    }
}
