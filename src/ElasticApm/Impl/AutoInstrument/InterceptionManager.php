<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\InterceptedFunctionCallTrackerInterface;
use Elastic\Apm\AutoInstrument\InterceptedMethodCallTrackerInterface;
use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\Assert;
use Elastic\Apm\Impl\Util\DbgUtil;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class InterceptionManager
{
    /** @var InterceptedCallRegistration[] */
    private $interceptedCallRegistrations;

    /** @var Logger */
    private $logger;

    public function __construct(Tracer $tracer)
    {
        $this->logger = $tracer->loggerFactory()
                               ->loggerForClass(LogCategory::INTERCEPTION, __NAMESPACE__, __CLASS__, __FILE__);

        $this->loadPlugins();
    }

    /**
     * @param int         $funcToInterceptId
     * @param object|null $thisObj
     * @param mixed       ...$interceptedCallArgs
     *
     * @return mixed
     * @throws Throwable
     */
    public function interceptedCall(int $funcToInterceptId, ?object $thisObj, ...$interceptedCallArgs)
    {
        $localLogger = $this->logger->inherit()->addContext('funcToInterceptId', $funcToInterceptId);

        $callTracker = $this->interceptedCallPreHook(
            $localLogger,
            $funcToInterceptId,
            $thisObj,
            ...$interceptedCallArgs
        );

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
     * @param Logger      $localLogger
     * @param int         $funcToInterceptId
     * @param object|null $thisObj
     * @param mixed       ...$interceptedCallArgs
     *
     * @return InterceptedMethodCallTrackerInterface|InterceptedFunctionCallTrackerInterface|null
     */
    private function interceptedCallPreHook(
        Logger $localLogger,
        int $funcToInterceptId,
        ?object $thisObj,
        ...$interceptedCallArgs
    ) {
        ($loggerProxy = $localLogger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered');

        $registration = $this->interceptedCallRegistrations[$funcToInterceptId];
        if ($registration->isMethod) {
            $localLogger->addContext('interceptedCallClass', $registration->className);
            $localLogger->addContext('interceptedCallMethod', $registration->methodName);
        } else {
            $localLogger->addContext('interceptedCallFunction', $registration->functionName);
        }

        try {
            /** @var InterceptedMethodCallTrackerInterface|InterceptedFunctionCallTrackerInterface $callTracker */
            $callTracker = ($registration->factory)();
        } catch (Throwable $throwable) {
            ($loggerProxy = $localLogger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                DbgUtil::fqToShortClassName(InterceptedMethodCallTrackerInterface::class)
                . ' factory has let a Throwable to escape - returning null',
                ['throwable' => $throwable]
            );
            return null;
        }

        try {
            if ($registration->isMethod) {
                ($assertProxy = Assert::ifEnabled())
                && $assertProxy->that(!is_null($thisObj))
                && $assertProxy->info('!is_null($thisObj)', ['registration' => $registration]);

                $callTracker->preHook($thisObj, ...$interceptedCallArgs);
            } else {
                ($assertProxy = Assert::ifEnabled())
                && $assertProxy->that(is_null($thisObj))
                && $assertProxy->info(
                    'is_null($thisObj)',
                    [
                        'registration' => $registration,
                        'thisObjType'  => DbgUtil::getType($thisObj),
                    ]
                );

                $callTracker->preHook(...$interceptedCallArgs);
            }
        } catch (Throwable $throwable) {
            ($loggerProxy = $localLogger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                DbgUtil::fqToShortClassName(InterceptedMethodCallTrackerInterface::class)
                . 'preHook() has let a Throwable to escape - returning null',
                ['throwable' => $throwable]
            );
            return null;
        }

        ($loggerProxy = $localLogger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Exiting - returning non-null');
        return $callTracker;
    }

    /**
     * @param Logger                                                                        $localLogger
     * @param InterceptedMethodCallTrackerInterface|InterceptedFunctionCallTrackerInterface $callTracker
     * @param bool                                                                          $hasExitedByException
     * @param mixed|Throwable                                                               $returnValueOrThrown
     *                          Return value of the intercepted call
     *                          or the object thrown by the intercepted call
     */
    private function interceptedCallPostHook(
        Logger $localLogger,
        $callTracker,
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
                DbgUtil::fqToShortClassName(InterceptedMethodCallTrackerInterface::class)
                . 'postHook() has let a Throwable to escape',
                ['throwable' => $throwable]
            );
        }
    }

    private function loadPlugins(): void
    {
        $registerCtx = new class implements RegistrationContextInterface {

            /** @var InterceptedCallRegistration[] */
            public $interceptedCallRegistrations;

            public function interceptCallsToMethod(
                string $className,
                string $methodName,
                callable $interceptedMethodCallTrackerFactory
            ): void {
                /**
                 * elasticapm_* functions are provided by the elasticapm extension
                 *
                 * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
                 * @phpstan-ignore-next-line
                 */
                $funcToInterceptId = \elasticapm_intercept_calls_to_internal_method($className, $methodName);
                if ($funcToInterceptId >= 0) {
                    $this->interceptedCallRegistrations[$funcToInterceptId] = InterceptedCallRegistration::forMethod(
                        $className,
                        $methodName,
                        $interceptedMethodCallTrackerFactory
                    );
                }
            }

            public function interceptCallsToFunction(
                string $functionName,
                callable $interceptedFunctionCallTrackerFactory
            ): void {
                /**
                 * elasticapm_* functions are provided by the elasticapm extension
                 *
                 * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
                 * @phpstan-ignore-next-line
                 */
                $funcToInterceptId = \elasticapm_intercept_calls_to_internal_function($functionName);
                if ($funcToInterceptId >= 0) {
                    $this->interceptedCallRegistrations[$funcToInterceptId] = InterceptedCallRegistration::forFunction(
                        $functionName,
                        $interceptedFunctionCallTrackerFactory
                    );
                }
            }
        };

        $this->loadPluginsImpl($registerCtx);

        $this->interceptedCallRegistrations = $registerCtx->interceptedCallRegistrations;
    }

    private function loadPluginsImpl(RegistrationContextInterface $registerCtx): void
    {
        (new BuiltinPlugin())->register($registerCtx);
        // self::loadConfiguredPlugins();
    }
}
